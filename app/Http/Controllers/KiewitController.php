<?php

namespace App\Http\Controllers;

use App\Kiewit\DistrictClientEngagementLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class KiewitController extends Controller
{
    /**
     * Create a new Kiewit controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function getAllDistrictCELeads()
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$data = [];

    		$ce_leads = DistrictClientEngagementLead::all();

    		foreach($ce_leads as $lead)
    		{
    			$data[] = \Metaclassing\Utility::decodeJson($lead['data']);
    		}

    		$response = [
    			'success'			=> true,
    			'count'				=> count($data),
    			'district_leads'	=> $data,
    		];

    	}
    	catch (\Exception $e)
    	{
	    	$response = [
	    		'success'	=> false,
	    		'message'	=> 'Failed to get District CE leads.',
	    	];
    	}

    	return response()->json($response);
    }

    /**
     * Populate the district_client_engagement_leads table with known leads.
     *
     * @return \Illuminate\Http\Response
     */
    public function createDistrictCELeadsList()
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$data = [];
    		$contents = file_get_contents(storage_path('app/collections/district_ce_leads.json'));
    		$district_leads = \Metaclassing\Utility::decodeJson($contents);

    		foreach ($district_leads as $lead)
    		{
    			$exists = DistrictClientEngagementLead::where('district', $lead['district'])->value('id');

    			if (!$exists)
    			{
    				Log::info('creating new '.$lead['district'].' CE lead '.$lead['email']);

    				$new_lead = new DistrictClientEngagementLead();

	    			$new_lead->district = $lead['district'];
	    			$new_lead->email = $lead['email'];
	    			$new_lead->data = \Metaclassing\Utility::encodeJson($lead);

	    			$new_lead->save();
	    		}
	    		else
	    		{
	    			$ce_lead = DistrictClientEngagementLead::find($exists);

	    			$ce_lead->updated([
	    				'email'	=> $lead['email'],
	    				'data'	=> \Metaclassing\Utility::encodeJson($lead),
	    			]);

	    			$ce_lead->save();

	    			// touch District CE lead model to update the 'updated_at' timestamp in case nothing was changed
	    			$ce_lead->touch();

	    			Log::info('updated '.$ce_lead->district.' CE lead to '.$ce_lead->email);
	    		}
    		}


    		$district_leads = DistrictClientEngagementLead::all();

    		foreach($district_leads as $lead)
    		{
    			$data[] = \Metaclassing\Utility::decodeJson($lead['data']);
    		}

	    	$response = [
	    		'success'			=> true,
	    		'district_leads'	=> $data,
	    	];

	    }
	    catch (\Exception $e)
	    {
	    	$response = [
	    		'success'	=> false,
	    		'message'	=> 'Failed to create District CE leads list.',
	    	];
	    }

	    return response()->json($response);
    }


    /**
     * Add new or update District CE lead model
     *
     * @return \Illuminate\Http\Response
     */
    public function updateDistrictCELeadsList($district, $lead_email)
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
			$tmp = [
				'district'	=> $district,
				'email'		=> $lead_email,
			];

    		$exists = DistrictClientEngagementLead::where('district', $district)->value('id');

    		if (!$exists)
    		{
    			$created_new = true;

    			Log::info('creating new '.$district.' CE lead '.$lead_email);

    			$new_lead = new DistrictClientEngagementLead();

    			$new_lead->district = $district;
    			$new_lead->email = $lead_email;
    			$new_lead->data = \Metaclassing\Utility::encodeJson($tmp);

    			$new_lead->save();
    		}
    		else
    		{
    			$created_new = false;

    			$ce_lead = DistrictClientEngagementLead::find($exists);

    			$ce_lead->update([
    				'email'	=> $lead_email,
    				'data'	=> \Metaclassing\Utility::encodeJson($tmp),
    			]);

    			$ce_lead->save();

    			// touch District CE lead model to update the 'updated_at' timestamp in case nothing changed
    			$ce_lead->touch();

    			Log::info('updated '.$ce_lead->district.' CE lead to '.$ce_lead->email);
    		}

    		$response = [
    			'success'		=> true,
    			'created_new'	=> $created_new,
    			'district'		=> $district,
    			'email'			=> $lead_email,
    		];

    	}
    	catch (\Exception $e)
    	{
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to update '.$district.' CE lead with '.$lead_email,
    		];
    	}

    	return response()->json($response);
    }

    /**
     * Delete District CE lead model
     *
     * @return \Illuminate\Http\Response
     */
    public function removeDistrictCELead($district)
    {
    	$user = JWTAuth::parseToken()->authenticate();

    	try {
    		$exists = DistrictClientEngagementLead::where('district', $district)->value('id');

    		if (!$exists)
    		{
    			$response = [
    				'success'	=> true,
    				'message'	=> 'No record exists for a '.$district.' CE lead',
    			];
    		}
    		else {
    			$ce_lead = DistrictClientEngagementLead::find($exists);

    			Log::info('deleting '.$ce_lead->district.' CE lead '.$ce_lead->email);

    			$ce_lead->delete();

    			$response = [
    				'success'	=> true,
    				'message'	=> 'Deleted '.$ce_lead->district.' CE lead '.$ce_lead->email,
    			];
    		}
    	}
    	catch (\Exception $e)
    	{
    		$response = [
    			'success'	=> false,
    			'message'	=> 'Failed to remove '.$district.' CE lead',
    		];
    	}

    	return response()->json($response);
    }
}
