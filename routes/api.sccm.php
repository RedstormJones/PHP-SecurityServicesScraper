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
    $api->get('/clear_upload', 'SCCMController@clearSCCMSystemsUpload');

    $api->post('/upload', 'SCCMController@uploadSCCMSystems');

    $api->get('/process_upload', 'SCCMController@processSCCMSystemsUpload');
});
