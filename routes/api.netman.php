<?php

$options = [
    'prefix'           => 'netman',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100,
    'expires'          => 5,
];



$api->group($options, function ($api) {
    $api->group(['prefix' => 'site_subnets'], function ($api) {
        $api->get('/list', 'NetmanSiteSubnetController@listAll');

        $api->get('/ip/{ip}', 'NetmanSiteSubnetController@getSiteSubnetByIP');

        $api->get('/netmask/{netmask}', 'NetmanSiteSubnetController@getSiteSubnetsByNetmask');

        $api->get('/site/{site}', 'NetmanSiteSubnetController@getSiteSubnetsBySite');
    });
});
