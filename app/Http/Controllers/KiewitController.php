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

	    			Log::info('updated '.$ce_lead->district.' CE lead '.$ce_lead->email);
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
}
