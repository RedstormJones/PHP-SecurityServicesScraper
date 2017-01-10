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