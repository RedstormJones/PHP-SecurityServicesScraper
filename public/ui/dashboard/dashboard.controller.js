(function() {

    'use strict';

    angular
        .module('PHP-SecurityServicesScraper')
        .controller('Dashboard.IndexController', controller);


    function controller ($location, UserService){
        var DashboardController = this;


        initController();

        DashboardController.userinfo = {};


        function initController() {
            //DashboardService.Render();

            UserService.Getuserinfo(function (result) {
                console.log('callback from UserService.userinfo responded ' + result);

                DashboardController.userinfo = UserService.userinfo;
                DashboardController.username = DashboardController.userinfo.cn[0];
                DashboardController.title = DashboardController.userinfo.title[0];
                DashboardController.photo = DashboardController.userinfo.thumbnailphoto[0];
                DashboardController.company = DashboardController.userinfo.company[0];
                DashboardController.department = DashboardController.userinfo.department[0];

                DashboardController.messages = JSON.stringify(DashboardController.userinfo, null, "    ");
            });

        };  // end of function initController()

    };  // end of function controller()

})();
