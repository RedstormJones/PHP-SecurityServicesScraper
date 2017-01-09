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

    // ServiceNow incidents route group
    $api->group(['prefix' => 'incidents'], function ($api) {

        // get incidents by caller
        $api->get('caller/{caller}', 'ServiceNowController@getIncidentsByCaller');
    });

    // ServiceNow Security incidents route group
    $api->group(['prefix' => 'security_incidents'], function ($api) {

        // get all incident tickets
        $api->get('/all', 'ServiceNowController@getAllSecurityIncidents');

        // get all active incident tickets
        $api->get('/active', 'ServiceNowController@getActiveSecurityIncidents');

        // get incidents tickets by District
        $api->get('/district/{district}', 'ServiceNowController@getSecurityIncidentsByDistrict');

        // get incident tickets by initial assignment group
        $api->get('/initial_group/{initial_group}', 'ServiceNowController@getSecurityIncidentsByInitialAssignGroup');

        // get incident tickets by priority
        $api->get('/priority/{priority}', 'ServiceNowController@getSecurityIncidentsByPriority');

        // get resolved incident tickets count by user
        $api->get('/resolved_by/user_count', 'ServiceNowController@getResolvedByUserCount');
    });

    // ServiceNow IDM incidents route group
    $api->group(['prefix' => 'idm_incidents'], function ($api) {

        // get all incident tickets
        $api->get('/all', 'ServiceNowController@getAllIDMIncidents');

        // get all active incident tickets
        $api->get('/active', 'ServiceNowController@getActiveIDMIncidents');

        // get resolved incident tickets count by user
        $api->get('/resolved_by/user_count', 'ServiceNowController@getResolvedByUserCount_IDM');
    });

    // ServiceNow SAP Role Auth incidents route group
    $api->group(['prefix' => 'sap_roleauth_incidents'], function ($api) {

        // get all incident tickets
        $api->get('/all', 'ServiceNowController@getAllSAPRoleAuthIncidents');

        // get all active incident tickets
        $api->get('/active', 'ServiceNowController@getActiveSAPRoleAuthIncidents');

        // get resolved incident tickets count by user
        $api->get('/resolved_by/user_count', 'ServiceNowController@getResolvedByUserCount_SAPRoleAuth');
    });
});
