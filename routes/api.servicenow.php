<?php

$options = [
    'prefix'        => 'servicenow',
    'namespace'     => 'App\Http\Controllers',
    'middleware'    => [
        'api.auth',
        'api.throttle',
    ],
    'limit'         => 100,
    'expires'       => 5,
];

$api->group($options, function ($api) {

    // ServiceNow CMDB servers route group
    $api->group(['prefix' => 'cmdbservers'], function ($api) {

        // get all CMDB servers
        $api->get('/all', 'ServiceNowController@getCMDBServers');

        // get CMDB server by name
        $api->get('/server/{name}', 'ServiceNowController@getCMDBServerByName');

        // get CMDB server by IP
        $api->get('/ip/{ip}', 'ServiceNowController@getCMDBServerByIP');

        // get CMDB servers by OS
        $api->get('/os/{os}', 'ServiceNowController@getCMDBServersByOS');

        // get CMDB servers by District
        $api->get('/district/{district}', 'ServiceNowController@getCMDBServersByDistrict');
    });

    // ServiceNow Security incidents route group
    $api->group(['prefix' => 'security_incident'], function ($api) {

        $api->get('/all', 'ServiceNowController@getAllSecurityIncidents');

        $api->get('/active', 'ServiceNowController@getActiveSecurityIncidents');

    });

    // ServiceNow IDM incidents route group
    $api->group(['prefix' => 'idm_incident'], function ($api) {
    });

    // ServiceNow SAP Role Auth incidents route group
    $api->group(['prefix' => 'sap_roleauth_incident'], function ($api) {
    });
});
