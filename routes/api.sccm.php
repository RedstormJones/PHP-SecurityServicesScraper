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
    // clear SCCM systems collection file
    $api->get('/clear_upload', 'SCCMController@clearSCCMSystemsUpload');

    // upload new SCCM systems data
    $api->post('/upload', 'SCCMController@uploadSCCMSystems');

    // initiate SCCM systems processing
    //$api->get('/process_upload', 'SCCMController@processSCCMSystemsUpload');

    // route group for reporting on SCCM systems data
    $api->group(['prefix' => 'reports'], function ($api) {

        // returns BitLocker compliance numbers
        $api->get('bitlocker_compliance', 'SCCMController@getBitLockerCompliance');

        // returns antivirus compliance numbers (Cylance & SCEP
        $api->get('antivirus_compliance', 'SCCMController@getAVCompliance');

        // returns AnyConnect compliance numbers
        $api->get('anyconnect_compliance', 'SCCMController@getAnyConnectCompliance');

        // returns AnyConnect Web Security compliance numbers
        $api->get('websecurity_compliance', 'SCCMController@getWebSecurityCompliance');
    });
});
