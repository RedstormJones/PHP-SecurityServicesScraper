(function() {

    'use strict';

    angular
        .module('PHP-SecurityServicesScraper')
        .controller('Login.IndexController', controller);

    function controller ($location, AuthenticationService) {
        var LoginController = this;

        LoginController.login = login;

        initController();

        function initController() {
            // reset login status
            AuthenticationService.Logout();
        };

        function login() {
            LoginController.loading = true;

            AuthenticationService.Login(LoginController.username, LoginController.password, function (result) {
                if (result === true) {
                    $location.path('/home');
                } else {
                    LoginController.error = 'Username or password is incorrect';
                    LoginController.loading = false;
                }
             });
        };

        // attempt to auto-authenticate
        LoginController.loading = true;
        AuthenticationService.Login('', '', function (result){
            if (result === true) {
                $location.path('/home');
            } else {
                LoginController.error = 'Automatic certificate authentication failed, please login with LDAP credentials';
                LoginController.loading = false;
            }
        });


    }   // end of function controller()

})();
