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
    $api->group(['prefix' => 'cmdbservers'], function ($api) {
    });


    $api->group(['prefix' => 'serviceNowIncidnt'], function ($apit) {
    });



    $api->group(['prefix' => 'serviceNowIdmIncidnt'], function ($apit) {
    });



    $api->group(['prefix' => 'serviceNowSapRoleAuthIncidnt'], function ($apit) {
    });
});