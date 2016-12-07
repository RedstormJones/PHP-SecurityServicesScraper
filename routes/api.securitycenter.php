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

	$api->group(['prefix' => 'asset_vulns'], function ($api) {

		$api->get('/all_assets', 'SecurityCenterController@getAllAssetVulns');

	});


});