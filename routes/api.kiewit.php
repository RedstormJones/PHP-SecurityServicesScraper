<?php

$options = [
    'prefix'           => 'kiewit',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100,
    'expires'          => 5,
];

$api->group($options, function ($api) {

    //$api->get('/create_district_leads_list', 'KiewitController@createDistrictCELeadsList');
});
