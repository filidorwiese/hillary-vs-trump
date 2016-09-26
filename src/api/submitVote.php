<?php
define('ROOTPATH', __DIR__ . '/../../');
define('STAGING', $_SERVER['SERVER_NAME'] === 'localhost');
define('LOGFILE', ROOTPATH . '/logs/votes.log');

require ROOTPATH . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
use GeoIp2\WebService\Client;

define('CLIENTIP', STAGING ? '1.0.0.0' : getIp());

//apiError('polls.closed');

$_CONFIG = parse_ini_file(ROOTPATH . '/config/config.ini', true);
$_DB = STAGING ? $_CONFIG['staging'] : $_CONFIG['production'];

// Validate client IP
if (filter_var(CLIENTIP, FILTER_VALIDATE_IP) === false) {
  apiError('request.invalid.ip');
}

$params = json_decode(file_get_contents('php://input'), true);
if (!count($params) || !isset($params['voted'])) { apiError('request.invalid.parameters'); }

// Verify token
if (!isset($params['token'])) { apiError('request.missing.token'); }


// Connect DB
$db = new mysqli($_DB['database.host'], $_DB['database.username'], $_DB['database.password']);
if ($db->connect_errno) {
  apiError('db.connect');
}
$db->select_db($_DB['database.dbname']);

// Test if IP already looked up
$results = $db->query("SELECT `country`, `hash`, `anon` FROM `country-lookup` WHERE `hash` = '" . sha1(CLIENTIP) . "' LIMIT 1");
if ($row = $results->fetch_array(MYSQLI_ASSOC)) {
  $countryCode = $row['country'];
} else {
  apiError('country.lookup.failed');
}

$token = sha1($row['hash'] . $_CONFIG['general']['hash.secret'] . date('Y-m-d'));
if ($params['token'] !== $token) {
  apiError('request.invalid.token');
}

// Test if ip already voted
$results = $db->query("SELECT `hash` FROM `votes` WHERE `hash` = '" . sha1(CLIENTIP) . "' LIMIT 1");
if ($results->num_rows) {
  apiError('request.not.unique');
}

// Insert vote into db
$db->query("INSERT INTO `votes` (`ts`, `hash`, `vote`, `country`)" .
    "VALUES ('" . time() . "', '" . sha1(CLIENTIP) . "', '" . mysqli_escape_string($db, $params['voted']) . "', '" . $countryCode . "')")
    or apiError('db.error');


logIt('Success. Vote = ' . $params['voted'] . ", Country = " . $countryCode);
die('OK');

