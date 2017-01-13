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

            foreach ($security_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            foreach ($idm_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            foreach ($sap_roleauth_tasks as $task) {
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

            $age_array['two_months']['count'] = 0;
            $age_array['two_months']['project_count'] = 0;
            $age_array['two_months']['project_task_count'] = 0;
            $age_array['two_months']['catalog_task_count'] = 0;
            
            $age_array['oneTotwo_months']['count'] = 0;
            $age_array['oneTotwo_months']['project_count'] = 0;
            $age_array['oneTotwo_months']['project_task_count'] = 0;
            $age_array['oneTotwo_months']['catalog_task_count'] = 0;

            $age_array['twoTofour_weeks']['count'] = 0;
            $age_array['twoTofour_weeks']['project_count'] = 0;
            $age_array['twoTofour_weeks']['project_task_count'] = 0;
            $age_array['twoTofour_weeks']['catalog_task_count'] = 0;

            $age_array['oneTotwo_weeks']['count'] = 0;
            $age_array['oneTotwo_weeks']['project_count'] = 0;
            $age_array['oneTotwo_weeks']['project_task_count'] = 0;
            $age_array['oneTotwo_weeks']['catalog_task_count'] = 0;

            $age_array['fiveToseven_days']['count'] = 0;
            $age_array['fiveToseven_days']['project_count'] = 0;
            $age_array['fiveToseven_days']['project_task_count'] = 0;
            $age_array['fiveToseven_days']['catalog_task_count'] = 0;

            $age_array['twoTofive_days']['count'] = 0;
            $age_array['twoTofive_days']['project_count'] = 0;
            $age_array['twoTofive_days']['project_task_count'] = 0;
            $age_array['twoTofive_days']['catalog_task_count'] = 0;

            $age_array['oneTotwo_days']['count'] = 0;
            $age_array['oneTotwo_days']['project_count'] = 0;
            $age_array['oneTotwo_days']['project_task_count'] = 0;
            $age_array['oneTotwo_days']['catalog_task_count'] = 0;

            $age_array['same_day']['count'] = 0;
            $age_array['same_day']['project_count'] = 0;
            $age_array['same_day']['project_task_count'] = 0;
            $age_array['same_day']['catalog_task_count'] = 0;

            foreach ($data as $task) {
                $task_created_date = substr($task['sys_created_on'], 0, -9);

                if ($task_created_date < $two_months)
                {
                    $age_array['two_months']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['two_months']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['two_months']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['two_months']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date < $four_weeks)
                {
                    $age_array['oneTotwo_months']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['oneTotwo_months']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['oneTotwo_months']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['oneTotwo_months']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date < $two_weeks)
                {
                    $age_array['twoTofour_weeks']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['twoTofour_weeks']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['twoTofour_weeks']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['twoTofour_weeks']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date < $seven_days)
                {
                    $age_array['oneTotwo_weeks']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['oneTotwo_weeks']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['oneTotwo_weeks']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['oneTotwo_weeks']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date < $five_days)
                {
                    $age_array['fiveToseven_days']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['fiveToseven_days']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['fiveToseven_days']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['fiveToseven_days']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date < $two_days)
                {
                    $age_array['twoTofive_days']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['twoTofive_days']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['twoTofive_days']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['twoTofive_days']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date < $today)
                {
                    $age_array['oneTotwo_days']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['oneTotwo_days']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['oneTotwo_days']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['oneTotwo_days']['catalog_task_count']++;
                    }
                }
                elseif ($task_created_date == $today)
                {
                    $age_array['same_day']['count']++;

                    if ($task['sys_class_name'] == 'Project')
                    {
                        $age_array['same_day']['project_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Project Task')
                    {
                        $age_array['same_day']['project_task_count']++;
                    }
                    elseif ($task['sys_class_name'] == 'Catalog Task')
                    {
                        $age_array['same_day']['catalog_task_count']++;
                    }
                }
            }

            $response = [
                'success'                      => true,
                'security_tasks_count'         => count($security_tasks),
                'idm_tasks_count'              => count($idm_tasks),
                'sap_roleauth_tasks_count'     => count($sap_roleauth_tasks),
                'total'                        => count($data),
                'age_array'                    => $age_array,
                //'tasks'                        => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'      => false,
                'message'      => 'Failed to get ServiceNow tasks.',
                'exception'    => $e->getMessage(),
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

            foreach ($idm_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            $response = [
                'success'              => true,
                'total'                => count($data),
                'idm_tasks'            => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get IDM tasks.',
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

            foreach ($sap_roleauth_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            $response = [
                'success'                  => true,
                'total'                    => count($data),
                'sap_roleauth_tasks'       => $data,
            ];
        } catch (\Exception $e) {
            $response = [
                'success'    => false,
                'message'    => 'Failed to get SAP role auth tasks.',
            ];
        }

        return response()->json($response);
    }
}
