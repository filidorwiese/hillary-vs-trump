
angular.module('Vote', [
    'ngSanitize',
    'ui.router',
    'vcRecaptcha'
  ])

  // @ngInject
  .config(($stateProvider, $urlRouterProvider, translateServiceProvider, vcRecaptchaServiceProvider) => {

    // Detect language
    const language = translateServiceProvider.detectLanguage([ 'en', 'nl' ]);
    // const language = 'nl';
    translateServiceProvider.setTranslation(`translations/${language}/labels.json`);
    translateServiceProvider.setFallbackTranslation(`translations/en/labels.json`);

    // States
    let translationsPromise;
    $stateProvider
      .state('root', {
        abstract: true,
        url: '',
        template: require('./views/root.html'),
        resolve: {
          translationsLoaded: (translateService) => {
            return translateService.isLoaded();
          }
        }
      })
      .state('root.index', {
        url: '/',
        template: require('./views/root.index.html')
      })
      .state('root.results', {
        url: '/results',
        template: require('./views/root.results.html')
      });

    // Redirect unknown states to the root state
    $urlRouterProvider
      .otherwise('/');

    // Initialize reCaptcha
    vcRecaptchaServiceProvider.setSiteKey('6LewDCoTAAAAAMpHUX9TQWjTPoCQ08SNBbdUk926')
  })

  // @ngInject
  .run((translateService) => {

    // Load translations
    translateService.loadTranslations();

  });
