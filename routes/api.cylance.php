<?php


$options = [
    'prefix'        => 'cylance',
    'namespace'     => 'App\Http\Controllers',
    'middleware'    => [
        'api.auth',
        'api.throttle',
    ],
    'limit'         => 100,
    'expires'       => 5,
];


$api->group($options, function ($api) {

    // Cylance Devices route group
    $api->group(['prefix' => 'devices'], function ($api) {

        // searches for a particular device by name
        $api->get('/{device_name}', 'CylanceController@getDevice');

        // queries for all devices where the provided username was last to be logged on
        $api->get('/user/{user_name}', 'CylanceController@listUsersDevices');

        // queries for all unsafe devices, ordered by count of unsafe files
        $api->get('/unsafe/list', 'CylanceController@listTopUnsafeDevices');

        // queries for all devices belonging to a particular District
        $api->get('/district/{district}', 'CylanceController@listDevicesByDistrict');

        // queries for any device with at least one IP matching the IP provided
        $api->get('/ip/{ip}', 'CylanceController@getDeviceByIP');

        // queries for any device with at least one MAC address matching the MAC address provided
        $api->get('/mac/{mac}', 'CylanceController@getDeviceByMAC');
    });


    // Cylance Threats route group
    $api->group(['prefix' => 'threats'], function ($api) {

        // searches for a particular threat by name
        $api->get('/filename/{file_name}', 'CylanceController@getThreatsByName');

        // queries for all unsafe files, ordered by Cylance score (desc)
        $api->get('/unsafe/list', 'CylanceController@listTopThreats');

        // queries for all threats categorized under a particular model
        $api->get('/model/{current_model}', 'CylanceController@getThreatsByModel');

        // queries for all threats detected by a particular detection mechanism
        $api->get('/detection/{detected_by}', 'CylanceController@getThreatsByDetection');

        // searches for a particular threat by MD5
        $api->get('/md5/{md5}', 'CylanceController@getThreatByMD5');

        // searched for a particular threat by SHA256
        $api->get('/sha256/{sha256}', 'CylanceController@getThreatBySHA256');
    });
});
