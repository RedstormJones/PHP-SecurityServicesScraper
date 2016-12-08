<?php

$options = [
    'prefix'           => 'securitycenter',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100,
    'expires'          => 5,
];

$api->group($options, function ($api) {

    // SecurityCenter asset vulnerabilities route group
    $api->group(['prefix' => 'asset_vulns'], function ($api) {

        // Get all asset vulnerabilities
        $api->get('/all_assets', 'SecurityCenterController@getAllAssetVulns');

        // Get asset vulnerabilities by asset score
        $api->get('/all_assets_by_score', 'SecurityCenterController@getAssetVulnsByScore');

        // Get asset vulnerabilities by asset name
        $api->get('/asset/{asset}', 'SecurityCenterController@getAssetVulnsByName');
    });
});
