<?php

$options = [
    'prefix'           => 'phishme',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100,
    'expires'          => 5,
];

$api->group($options, function ($api) {
    
    $api->group(['prefix' => 'reports'], function ($api){

        // query for the results of a particular KTG click test, given a date in the format of Y-M (i.e. 2016-AUG)
        $api->post('/ktg/{date}', 'PhishMeController@getKTGClickTestResults');

        // query for the results of a particular Enterprise click test, given a date in the formart of Y-M (i.e. 2016-AUG)
        $api->post('/enterprise/{date}/{district}', 'PhishMeController@getEnterpriseClickTestResults');

    });

    // Attachment scenarios route group
    $api->group(['prefix' => 'attachment_scenarios'], function ($api) {

        // get attachment scenarios for a given user
        $api->get('/user/{user_name}', 'PhishMeController@getUserAttachmentScenarios');
    });

    // Click only scenarios route group
    $api->group(['prefix' => 'click_only_scenarios'], function ($api) {

        // get click only scenarios for a given user
        $api->get('/user/{user_name}', 'PhishMeController@getUserClickOnlyScenarios');
    });

    // Data entry scenarios route group
    $api->group(['prefix' => 'data_entry_scenarios'], function ($api) {

        // get data entry scenarios for a given user
        $api->get('/user/{user_name}', 'PhishMeController@getUserDataEntryScenarios');
    });
});
