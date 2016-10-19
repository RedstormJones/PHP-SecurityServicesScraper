/*
    This is a generic function inside of an enclosure. This enclosure
    will isolate this script from any other JavaScript that might be in
    a page that you put this into.
/**/

(function() {
    'use strict';

    angular
        .module('PHP-SecurityServicesScraper', ['ui.router', 'ngMessages', 'ngStorage', 'angular-jwt'])
        .config(config)
        .run(run);

    function config($stateProvider, $urlRouterProvider){

        // default route
        $urlRouterProvider.otherwise('/');

        // app routes
        $stateProvider
            .state('home', {
                url: '/',
                templateUrl: 'home/home.view.html',
                controller: 'Home.IndexController',
                controllerAs: 'HomeController',
            })
            .state('login', {
                url: '/login',
                templateUrl: 'login/login.view.html',
                controller: 'Login.IndexController',
                controllerAs: 'LoginController',
            })
            .state('dashboard', {
                url: '/dashboard',
                templateUrl: 'dashboard/dashboard.view.html',
                controller: 'Dashboard.IndexController',
                controllerAs: 'DashboardController',
            });

    };   // end of function config()

    function run($rootScope, $http, $location, $localStorage, jwtHelper){

        // keep user logged in after page refresh
        if ($localStorage.currentUser) {
            console.log('Found local storage login token: ' + $localStorage.currentUser.token);
            if (jwtHelper.isTokenExpired($localStorage.currentUser.token)) {
                console.log('Cached token is expired, logging out');
                delete $localStorage.currentUser;
                $http.defaults.headers.common.Authorization = '';
            } else {
                console.log('Cached token is still valid');
                $http.defaults.headers.common.Authorization = 'Bearer ' + $localStorage.currentUser.token;
            }
        }

        // redirect to login page if not logged in and trying to access a restricted page
        $rootScope.$on('$locationChangeStart', function(event, next, current) {
            var publicPages = ['/login'];
            var restrictedPage = publicPages.indexOf($location.path()) === -1;

            if (restrictedPage && !$localStorage.currentUser) {
                $location.path('/login');
            }
        });

    }   // end of function run()

})();

