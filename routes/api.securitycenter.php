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

    // SecurityCenter vulnerability severity route group
    $api->group(['prefix' => 'severity'], function ($api) {

        // get count of vulnerabilities for each severity (critical, high, medium)
        $api->get('/counts', 'SecurityCenterController@getVulnerabilityCounts');

        // get top ten most vulnerable hosts
        $api->get('/top_ten_hosts', 'SecurityCenterController@getTopTenVulnerableHosts');

        // Get all vulnerabilities for a specific severity (critical, high, medium)
        $api->get('/{severity}/all', 'SecurityCenterController@getVulnerabilities');

        // Get vulnerabilities for a particular device by device name (critical, high, medium, all)
        $api->get('/{severity}/device/{device}', 'SecurityCenterController@getVulnsByDevice');

        // Get vulnerabilities for a particular device by IP (critical, high, medium, all)
        $api->get('/{severity}/ip/{ip}', 'SecurityCenterController@getVulnsByIP');

        // Get vulnerabilities with exploits available for a particular severity (critical, high, medium)
        $api->get('/{severity}/exploit_available', 'SecurityCenterController@getVulnsWithExploit');

        // Get vulnerabilities with exploits available for a particular device by device name (critical, high, medium, all)
        $api->get('/{severity}/exploit_available/device/{device}', 'SecurityCenterController@getVulnsWithExploitForDevice');

        // Get vulnerabilities with exploits available for a particular device by IP (critical, high, medium, all)
        $api->get('/{severity}/exploit_available/ip/{ip}', 'SecurityCenterController@getVulnsWithExploitForIP');
    });
});
