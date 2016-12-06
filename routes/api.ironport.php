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

    // IronPort incoming email route group
    $api->group(['prefix' => 'incoming_email'], function ($api) {

        // get total count of incoming email
        $api->get('/total_count', 'IronPortController@getTotalCount');

        // get count of incoming email for a specific date range
        $api->get('/count/from/{from_date}/to/{to_date}', 'IronPortController@getEmailCountInDateRange');

        // get incoming emails for a specific sending domain
        $api->get('/sending_domain/{sending_domain}', 'IronPortController@getEmailsBySendingDomain');

        // get incoming emails for a specific date range
        $api->get('/from/{from_date}/to/{to_date}', 'IronPortController@getEmailsInDateRange');
    });

    // IronPort threats route group
    $api->group(['prefix' => 'threats'], function ($api) {

        // get all IronPort threats
        $api->get('/all_threats', 'IronPortController@getAllThreats');

        // get count of all IronPort threats
        $api->get('/total_threat_count', 'IronPortController@getTotalThreatCount');

        // get count of IronPort threats for a specific date
        $api->get('/threat_count_by_date/{date}', 'IronPortController@getThreatCountByDate');
    });

    // IronPort spam route group
    $api->group(['prefix' => 'spam'], function ($api) {
        $api->get('/total_spam_count', 'IronPortController@getTotalSpamCount');
    });
});
