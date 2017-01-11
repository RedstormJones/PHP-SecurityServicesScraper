<?php

namespace App\Http\Controllers;

use App\ServiceNow\ServiceNowSecurityTask;
use App\ServiceNow\ServiceNowIdmTask;
use App\ServiceNow\ServiceNowSapRoleAuthTask;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;

class ServiceNowTaskController extends Controller
{

    /**
     * Create a new Cylance Controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

	/**
     * Get all ServiceNow tasks.
     *
     * @return \Illuminate\Http\Response
     */
	public function getOpenInfoSecTasks()
	{
		$user = JWTAuth::parseToken()->authenticate();

		try {
			$data = [];

			// get security tasks
			$security_tasks = ServiceNowSecurityTask::where([
				['active', 'true'],
				['state', '!=', 'Resolved'],
				['class_name', '!=', 'Change Task'],
				['class_name', '!=', 'Request for Change'],
				['created_on', '>=', '2016-01-01 00:00:00'],
			])->get();

			// get IDM tasks
			$idm_tasks = ServiceNowIdmTask::where([
				['active', 'true'],
				['state', '!=', 'Resolved'],
				['class_name', '!=', 'Change Task'],
				['class_name', '!=', 'Request for Change'],
				['created_on', '>=', '2016-01-01 00:00:00'],
			])->get();

			// get SAP role auth tasks
			$sap_roleauth_tasks = ServiceNowSapRoleAuthTask::where([
				['active', 'true'],
				['state', '!=', 'Resolved'],
				['class_name', '!=', 'Change Task'],
				['class_name', '!=', 'Request for Change'],
				['created_on', '>=', '2016-01-01 00:00:00'],
			])->get();

			foreach($security_tasks as $task)
			{
				$data[] = \Metaclassing\Utility::decodeJson($task['data']);
			}

			foreach($idm_tasks as $task)
			{
				$data[] = \Metaclassing\Utility::decodeJson($task['data']);
			}

			foreach($sap_roleauth_tasks as $task)
			{
				$data[] = \Metaclassing\Utility::decodeJson($task['data']);
			}

	        $today = new \DateTime('now');
	        $two_days = $today->modify('-2 days')->format('Y-m-d');
	        $five_days = $today->modify('-5 days')->format('Y-m-d');
	        $seven_days = $today->modify('-7 days')->format('Y-m-d');
	        $two_weeks = $today->modify('-2 weeks')->format('Y-m-d');
	        $four_weeks = $today->modify('-4 weeks')->format('Y-m-d');
	        $two_months = $today->modify('-2 months')->format('Y-m-d');
	        $today = $today->format('Y-m-d H:i:s');

	        $age_array = [];
	        $age_array['2months'] = 0;
	        $age_array['1_2months'] = 0;
	        $age_array['2_4weeks'] = 0;
	        $age_array['1_2weeks'] = 0;
	        $age_array['5_7days'] = 0;
	        $age_array['2_5days'] = 0;
	        $age_array['1_2days'] = 0;
	        $age_array['same_day'] = 0;

			foreach($data as $task)
			{
				$task_created_date = substr($task['sys_created_on'], 0, -9);

				if ($task_created_date < $two_months)
				{
					$age_array['2months']++;
				}
				elseif ($task_created_date < $four_weeks)
				{
					$age_array['1_2months']++;
				}
				elseif ($task_created_date < $two_weeks)
				{
					$age_array['2_4weeks']++;
				}
				elseif ($task_created_date < $seven_days)
				{
					$age_array['1_2weeks']++;
				}
				elseif ($task_created_date < $five_days)
				{
					$age_array['5_7days']++;
				}
				elseif ($task_created_date < $two_days)
				{
					$age_array['2_5days']++;
				}
				elseif ($task_created_date < $today)
				{
					$age_array['1_2days']++;
				}
				elseif ($task_created_date == $today)
				{
					$age_array['same_day']++;
				}
			}

			$response = [
				'success'					=> true,
				'security_tasks_count'		=> count($security_tasks),
				'idm_tasks_count'			=> count($idm_tasks),
				'sap_roleauth_tasks_count'	=> count($sap_roleauth_tasks),
				'total'						=> count($data),
				'age_array'					=> $age_array,
				'tasks'						=> $data,
			];

		}
		catch (\Exception $e)
		{
			$response = [
				'success'	=> false,
				'message'	=> 'Failed to get ServiceNow tasks.',
				'exception'	=> $e->getMessage(),
			];
		}

		return response()->json($response);
	}

	/**
     * Get all security tasks.
     *
     * @return \Illuminate\Http\Response
     */
	public function getAllSecurityTasks()
	{
		$user = JWTAuth::parseToken()->authenticate();

		try {
			$data = [];

			$security_tasks = ServiceNowSecurityTask::all();

			foreach($security_tasks as $task)
			{
				$data[] = \Metaclassing\Utility::decodeJson($task['data']);
			}

			$response = [
				'success'			=> true,
				'total'				=> count($data),
				'security_tasks'	=> $data,
			];

		}
		catch (\Exception $e)
		{
			$response = [
				'success'	=> false,
				'message'	=> 'Failed to get Security tasks.',
			];
		}

		return response()->json($response);
	}



	/**
     * Get all IDM tasks.
     *
     * @return \Illuminate\Http\Response
     */
	public function getAllIDMTasks()
	{
		$user = JWTAuth::parseToken()->authenticate();

		try {
			$data = [];

			$idm_tasks = ServiceNowIdmTask::all();

			foreach($idm_tasks as $task)
			{
				$data[] = \Metaclassing\Utility::decodeJson($task['data']);
			}

			$response = [
				'success'			=> true,
				'total'				=> count($data),
				'idm_tasks'	=> $data,
			];

		}
		catch (\Exception $e)
		{
			$response = [
				'success'	=> false,
				'message'	=> 'Failed to get IDM tasks.',
			];
		}

		return response()->json($response);
	}


	/**
     * Get all SAP role auth tasks.
     *
     * @return \Illuminate\Http\Response
     */
	public function getAllSAPRoleAuthTasks()
	{
		$user = JWTAuth::parseToken()->authenticate();

		try {
			$data = [];

			$sap_roleauth_tasks = ServiceNowSapRoleAuthTask::all();

			foreach($sap_roleauth_tasks as $task)
			{
				$data[] = \Metaclassing\Utility::decodeJson($task['data']);
			}

			$response = [
				'success'				=> true,
				'total'					=> count($data),
				'sap_roleauth_tasks'	=> $data,
			];

		}
		catch (\Exception $e)
		{
			$response = [
				'success'	=> false,
				'message'	=> 'Failed to get SAP role auth tasks.',
			];
		}

		return response()->json($response);
	}

}
