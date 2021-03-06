<?php
$startTime = microtime(true);

define('ROOTPATH', __DIR__ . '/../../');;
define('STAGING', gethostname() !== 'hillary-vs-trump');
define('LOGFILE', ROOTPATH . '/logs/votes.log');

require ROOTPATH . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
use GeoIp2\WebService\Client;

define('CLIENTIP', STAGING ? '1.0.0.0' : getIp());

$_CONFIG = parse_ini_file(ROOTPATH . '/config/config.ini', true);
$_DB = STAGING ? $_CONFIG['staging'] : $_CONFIG['production'];

// Closing polls on the day of election
if (time() > 1478563200) {
  apiError('polls.closed');
}

// Validate client IP
if (filter_var(CLIENTIP, FILTER_VALIDATE_IP) === false) {
  apiError('request.invalid.ip');
}

$params = json_decode(file_get_contents('php://input'), true);
if (!count($params) || !isset($params['voted'])) { apiError('request.invalid.parameters'); }

// Verify token
$tokenValid = true;
if (!(isset($params['t']) && isset($params['n']) && isset($params['p']))) {
  apiError('request.missing.token');
}

// Connect DB
$db = new mysqli($_DB['database.host'], $_DB['database.username'], $_DB['database.password']);
if ($db->connect_errno) {
  apiError('db.connect');
}
$db->select_db($_DB['database.dbname']);

$hashedIp = sha1($_CONFIG['general']['ip.salt'] . sha1(CLIENTIP));

// Test if IP already looked up
$results = $db->query("SELECT `country`, `hash` FROM `country-lookup` WHERE `hash` = '" . $hashedIp . "' LIMIT 1");
if ($row = $results->fetch_array(MYSQLI_ASSOC)) {
  $countryCode = $row['country'];
} else {
  apiError('country.lookup.failed');
}

$token = sha1($row['hash'] . $_CONFIG['general']['hash.secret'] . date('Y-m-d'));
$t = base64_encode($token . ':' . $_CONFIG['general']['token.salt']);
$n = crc32(base64_encode($_CONFIG['general']['nonce.salt'] . $params['voted'] . ':' . $token));

if ($t !== $params['t'] || $n !== $params['n']) {
  apiError('request.invalid.token');
}

// Test if ip already voted within past 24 hours
$results = $db->query("SELECT `hash` FROM `votes` WHERE `hash` = '" . $hashedIp . "' AND `ts` > " . (time() - (24*60*60)) . " LIMIT 1");
if ($results->num_rows) {
  apiError('request.not.unique');
}

// Insert vote into db
$db->query("INSERT INTO `votes` (`ts`, `hash`, `vote`, `country`)" .
  "VALUES ('" . time() . "', '" . $hashedIp . "', '" . mysqli_escape_string($db, $params['voted']) . "', '" . $countryCode . "')");

if ($db->error) {
  file_put_contents(ROOTPATH . '/logs/dberror.log', $db->error . PHP_EOL, FILE_APPEND);
  apiError('db.error');
}

$endTime = microtime(true) - $startTime;
logIt('Success. Vote = ' . $params['voted'] . ", Country = " . $countryCode . ' (' . $endTime . 'ms)');


echo json_encode(array('ts' => time()));
die;

