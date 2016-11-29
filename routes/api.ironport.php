<?php

$options = [
    'prefix'           => 'ironport',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100,
    'expires'          => 5,
];


$api->group($options, function ($api) {
    $api->group(['prefix' => 'incoming_email'], function ($api) {
        $api->get('/total_count', 'IronPortController@getTotalCount');

        $api->get('/count/from/{from_date}/to/{to_date}', 'IronPortController@getEmailCountInDateRange');

        $api->get('/sending_domain/{sending_domain}', 'IronPortController@getEmailsBySendingDomain');

        $api->get('/from/{from_date}/to/{to_date}', 'IronPortController@getEmailsInDateRange');
    });
});
