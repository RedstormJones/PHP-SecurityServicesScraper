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

        // get all Cylance Devices
        $api->get('/all_devices', 'CylanceController@getAllDevices');

        // queries for devices with the most quarantined files (top 10)
        $api->get('/top_quarantined', 'CylanceController@getTopQuarantined');

        // get per-device average of quarantined files for each District
        $api->get('/quarantined_averages', 'CylanceController@getQuarantinedAvgsByDistrict');

        // get device count of all agent versions
        $api->get('/agent_versions', 'CylanceController@getDeviceAgentVersions');

        // get device count over time
        $api->get('/device_count_over_time', 'CylanceController@getDevicesCountOverTime');

        // searches for a particular device by name
        $api->get('/{device_name}', 'CylanceController@getDevice');

        // queries for all devices where the provided username was last to be logged on
        $api->get('/user/{user_name}', 'CylanceController@listUsersDevices');

        // queries for all unsafe devices, ordered by count of unsafe files
        $api->get('/unsafe/list', 'CylanceController@getAllUnsafeDevices');

        // get unsafe devices count for each District
        $api->get('/unsafe/by_district', 'CylanceController@getUnsafeDevicesForDistricts');

        // queries for all devices belonging to a particular District
        $api->get('/district/{district}', 'CylanceController@listDevicesByDistrict');

        // queries for any device with at least one IP matching the IP provided
        $api->get('/ip/{ip}', 'CylanceController@getDeviceByIP');

        // queries for any device with at least one MAC address matching the MAC address provided
        $api->get('/mac/{mac}', 'CylanceController@getDeviceByMAC');

        // get list of last logged on users and the timestamps of their activity for a particular device
        $api->get('/ownership_history/{device_name}', 'CylanceController@getDeviceOwnerHistory');
    });

    // Cylance Threats route group
    $api->group(['prefix' => 'threats'], function ($api) {

        // get all Cylance threats
        $api->get('/all_threats', 'CylanceController@getAllThreats');

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
