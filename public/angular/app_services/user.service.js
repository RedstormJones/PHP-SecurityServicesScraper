(function () {
	'use strict';

    angular
        .module('PHP-SecurityServicesScraper')
        .factory('UserService', Service);

    function Service($http, $localStorage) {
        var service = {};

        service.Getuserinfo = Getuserinfo;

		service.userinfo = {};

		function Getuserinfo(callback) {
			service.userinfo = {};
			GetType(callback, 'userinfo');
        }

        function GetType(callback, type) {
			service.userinfo[type] = {};
            $http.get('../api/' + type)
                .success(function (response) {
					//console.log(response);
					service.userinfo = response;
					//console.log(service.userinfo);
					callback(true);
                })
				// execute callback with false to indicate failed call
				.error(function() {
					callback(false);
				});
		}

		return service;
    }
})();
