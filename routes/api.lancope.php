<?php


$options = [
    'prefix'        => 'lancope',
    'namespace'     => 'App\Http\Controllers',
    'middleware'    => [
        'api.auth',
        'api.throttle',
    ],
    'limit'         => 100,
    'expires'       => 5,
];


$api->group($options, function ($api) {

	// Inside host traffic route group
	$api->group(['prefix' => 'inside_host_traffic'], function ($api) {

		// get aggregate statistics on inside host traffic
		$api->get('/aggregates', 'LancopeController@getInsideHostTrafficAggregates');

	});


});