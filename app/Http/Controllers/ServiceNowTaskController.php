<?php

namespace App\Http\Controllers;

use App\ServiceNow\ServiceNowIdmTask;
use App\ServiceNow\ServiceNowSapRoleAuthTask;
use App\ServiceNow\ServiceNowSecurityTask;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

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
    public function getInfoSecTasks()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            // get security tasks
            $security_tasks = ServiceNowSecurityTask::where([
                ['active', '=', 'true'],
                ['state', '!=', 'Resolved'],
                ['class_name', '!=', 'Change Task'],
                ['class_name', '!=', 'Request for Change'],
                //['created_on', '>', '2016-01-01 00:00:00'],
            ])->get();

            // get IDM tasks
            $idm_tasks = ServiceNowIdmTask::where([
                ['active', '=', 'true'],
                ['state', '!=', 'Resolved'],
                ['class_name', '!=', 'Change Task'],
                ['class_name', '!=', 'Request for Change'],
                //['created_on', '>', '2016-01-01 00:00:00'],
            ])->get();

            // get SAP role auth tasks
            $sap_roleauth_tasks = ServiceNowSapRoleAuthTask::where([
                ['active', '=', 'true'],
                ['state', '!=', 'Resolved'],
                ['class_name', '!=', 'Change Task'],
                ['class_name', '!=', 'Request for Change'],
                //['created_on', '>', '2016-01-01 00:00:00'],
            ])->get();

            foreach ($security_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            foreach ($idm_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            foreach ($sap_roleauth_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            $response = [
                'success'                      => true,
                'total'                        => count($data),
                'security_task_count'          => count($security_tasks),
                'idm_task_count'               => count($idm_tasks),
                'sap_roleauth_task_count'      => count($sap_roleauth_tasks),
                'tasks'                        => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get ServiceNow tasks.',
            ];
        }

        return resposne()->json($response);
    }

    public function getAllSecurityTasks()
    {
        $user = JWTAuth::parseToken()->authenticate();

        try {
            $data = [];

            $security_tasks = ServiceNowSecurityTask::all();

            foreach ($security_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            $response = [
                'success'              => true,
                'total'                => count($data),
                'security_tasks'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get Security tasks.',
            ];
        }

        return response()->json($response);
    }
}
