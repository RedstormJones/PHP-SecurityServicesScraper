(function() {

    'use strict';

    angular
        .module('PHP-SecurityServicesScraper')
        .controller('Login.IndexController', controller);

    function controller ($location, AuthenticationService) {
        var loginController = this;

        loginController.login = login;

        initController();

        function initController() {
            // reset login status
            AuthenticationService.Logout();
        };

        function login() {
            loginController.loading = true;

            AuthenticationService.Login(loginController.username, loginController.password, function (result) {
                if (result === true) {
                    $location.path('/home');
                } else {
                    loginController.error = 'Username or password is incorrect';
                    loginController.loading = false;
                }
             });
        };

        // attempt to auto-authenticate
        loginController.loading = true;
        AuthenticationService.Login('', '', function (result){
            if (result === true) {
                $location.path('/home');
            } else {
                loginController.error = 'Automatic certificate authentication failed, please login with LDAP credentials';
                loginController.loading = false;
            }
        });


    }   // end of function controller()

})();
