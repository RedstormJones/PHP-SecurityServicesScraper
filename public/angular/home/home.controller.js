(function () {

    'use strict';

    angular
        .module('PHP-SecurityServicesScraper')
        .controller('Home.IndexController', controller);


    function controller ($location, UserService) {
        var homeController = this;


        initController();

        homeController.messages = 'Loading user info...';
        homeController.userinfo = {};

        function initController() {

            UserService.Getuserinfo(function (result) {
                console.log('callback from UserService.userinfo responded ' + result);

                homeController.userinfo = UserService.userinfo;
                homeController.username = homeController.userinfo.cn[0];
                homeController.title = homeController.userinfo.title[0];
                homeController.photo = homeController.userinfo.thumbnailphoto[0];

                homeController.messages = JSON.stringify(homeController.userinfo, null, "    ");

            });

        }   // end of function initController()

    }   // end of function controller()

})();
