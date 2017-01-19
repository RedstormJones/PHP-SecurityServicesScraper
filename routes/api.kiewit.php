<?php

$options = [
    'prefix'           => 'kiewit',
    'namespace'        => 'App\Http\Controllers',
    'middleware'       => [
        'api.auth',
        'api.throttle',
    ],
    'limit'            => 100,
    'expires'          => 5,
];

$api->group($options, function ($api) {

	$api->group(['prefix' => 'district_leads'], function ($api) {

		$api->get('/all', 'KiewitController@getAllDistrictCELeads');

	    //$api->get('/create_district_leads_list', 'KiewitController@createDistrictCELeadsList');
	    
	    $api->post('/update_lead/{district}/{lead_email}', 'KiewitController@updateDistrictCELeadsList');

	    $api->post('/remove_lead/{district}', 'KiewitController@removeDistrictCELead');
	});

});
