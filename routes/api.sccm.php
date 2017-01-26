<?php

$options = [
    'prefix'           => 'sccm',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100000,
    'expires'          => 5,
];

$api->group($options, function ($api) {	
    $api->post('/upload', 'SCCMController@uploadAllSystems');

	$api->get('/clear_upload', 'SCCMController@clearAllSystemsUpload');
});
