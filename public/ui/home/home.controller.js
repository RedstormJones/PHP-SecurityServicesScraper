(function () {

    'use strict';

    angular
        .module('PHP-SecurityServicesScraper')
        .controller('Home.IndexController', controller);


    function controller ($location, UserService) {
        var HomeController = this;


        initController();

        HomeController.messages = 'Loading user info...';
        HomeController.userinfo = {};

        function initController() {

            UserService.Getuserinfo(function (result) {
                console.log('callback from UserService.userinfo responded ' + result);

                HomeController.userinfo = UserService.userinfo;
                HomeController.username = HomeController.userinfo.cn[0];
                HomeController.title = HomeController.userinfo.title[0];
                HomeController.photo = HomeController.userinfo.thumbnailphoto[0];
                HomeController.company = HomeController.userinfo.company[0];
                HomeController.department = HomeController.userinfo.department[0];

                HomeController.messages = JSON.stringify(HomeController.userinfo, null, "    ");

            });

        }   // end of function initController()

    }   // end of function controller()

})();
