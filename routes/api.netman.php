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

    // Netman site subnets route group
    $api->group(['prefix' => 'site_subnets'], function ($api) {

        // list all site subnets
        $api->get('/list', 'NetmanSiteSubnetController@listAll');

        // get site subnets by IP address
        $api->get('/ip/{ip}', 'NetmanSiteSubnetController@getSiteSubnetByIP');

        // get site subnets by netmask value
        $api->get('/netmask/{netmask}', 'NetmanSiteSubnetController@getSiteSubnetsByNetmask');

        // get site subnets by job site name
        $api->get('/site/{site}', 'NetmanSiteSubnetController@getSiteSubnetsBySite');
    });
});
