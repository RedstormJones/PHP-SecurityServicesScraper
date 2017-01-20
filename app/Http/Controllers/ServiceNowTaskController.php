<?php

namespace App\Http\Controllers;

use App\ServiceNow\ServiceNowIdmTask;
use App\ServiceNow\ServiceNowSapRoleAuthTask;
use App\ServiceNow\ServiceNowSecurityTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                ['active', '=', 'true'],
                ['state', '!=', 'Resolved'],
                ['class_name', '!=', 'Change Task'],
                ['class_name', '!=', 'Request for Change'],
                //['created_on', '>=', '2016-01-01 00:00:00'],
            ])->get();

            // get IDM tasks
            $idm_tasks = ServiceNowIdmTask::where([
                ['active', '=', 'true'],
                ['state', '!=', 'Resolved'],
                ['class_name', '!=', 'Change Task'],
                ['class_name', '!=', 'Request for Change'],
                //['created_on', '>=', '2016-01-01 00:00:00'],
            ])->get();

            // get SAP role auth tasks
            $sap_roleauth_tasks = ServiceNowSapRoleAuthTask::where([
                ['active', '=', 'true'],
                ['state', '!=', 'Resolved'],
                ['class_name', '!=', 'Change Task'],
                ['class_name', '!=', 'Request for Change'],
                //['created_on', '>=', '2016-01-01 00:00:00'],
            ])->get();

            // cycle through each task type and build data array
            foreach ($security_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            foreach ($idm_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            foreach ($sap_roleauth_tasks as $task) {
                $data[] = \Metaclassing\Utility::decodeJson($task['data']);
            }

            // setup time constraint variables
            $today = Carbon::now();
            $two_days = Carbon::now()->subDays(2);
            $five_days = Carbon::now()->subDays(5);
            $seven_days = Carbon::now()->subWeek();
            $two_weeks = Carbon::now()->subWeeks(2);
            $four_weeks = Carbon::now()->subMonth();
            $two_months = Carbon::now()->subMonths(2);

            // setup array to hold distribution of task age counts
            $task_age_counts = [];

            $task_age_counts['two_months']['count'] = 0;
            $task_age_counts['two_months']['project_count'] = 0;
            $task_age_counts['two_months']['project_task_count'] = 0;
            $task_age_counts['two_months']['catalog_task_count'] = 0;
            $task_age_counts['two_months']['incident_count'] = 0;
            $task_age_counts['two_months']['incident_task_count'] = 0;

            $task_age_counts['oneTotwo_months']['count'] = 0;
            $task_age_counts['oneTotwo_months']['project_count'] = 0;
            $task_age_counts['oneTotwo_months']['project_task_count'] = 0;
            $task_age_counts['oneTotwo_months']['catalog_task_count'] = 0;
            $task_age_counts['oneTotwo_months']['incident_count'] = 0;
            $task_age_counts['oneTotwo_months']['incident_task_count'] = 0;

            $task_age_counts['twoTofour_weeks']['count'] = 0;
            $task_age_counts['twoTofour_weeks']['project_count'] = 0;
            $task_age_counts['twoTofour_weeks']['project_task_count'] = 0;
            $task_age_counts['twoTofour_weeks']['catalog_task_count'] = 0;
            $task_age_counts['twoTofour_weeks']['incident_count'] = 0;
            $task_age_counts['twoTofour_weeks']['incident_task_count'] = 0;

            $task_age_counts['oneTotwo_weeks']['count'] = 0;
            $task_age_counts['oneTotwo_weeks']['project_count'] = 0;
            $task_age_counts['oneTotwo_weeks']['project_task_count'] = 0;
            $task_age_counts['oneTotwo_weeks']['catalog_task_count'] = 0;
            $task_age_counts['oneTotwo_weeks']['incident_count'] = 0;
            $task_age_counts['oneTotwo_weeks']['incident_task_count'] = 0;

            $task_age_counts['fiveToseven_days']['count'] = 0;
            $task_age_counts['fiveToseven_days']['project_count'] = 0;
            $task_age_counts['fiveToseven_days']['project_task_count'] = 0;
            $task_age_counts['fiveToseven_days']['catalog_task_count'] = 0;
            $task_age_counts['fiveToseven_days']['incident_count'] = 0;
            $task_age_counts['fiveToseven_days']['incident_task_count'] = 0;

            $task_age_counts['twoTofive_days']['count'] = 0;
            $task_age_counts['twoTofive_days']['project_count'] = 0;
            $task_age_counts['twoTofive_days']['project_task_count'] = 0;
            $task_age_counts['twoTofive_days']['catalog_task_count'] = 0;
            $task_age_counts['twoTofive_days']['incident_count'] = 0;
            $task_age_counts['twoTofive_days']['incident_task_count'] = 0;

            $task_age_counts['oneTotwo_days']['count'] = 0;
            $task_age_counts['oneTotwo_days']['project_count'] = 0;
            $task_age_counts['oneTotwo_days']['project_task_count'] = 0;
            $task_age_counts['oneTotwo_days']['catalog_task_count'] = 0;
            $task_age_counts['oneTotwo_days']['incident_count'] = 0;
            $task_age_counts['oneTotwo_days']['incident_task_count'] = 0;

            $task_age_counts['same_day']['count'] = 0;
            $task_age_counts['same_day']['project_count'] = 0;
            $task_age_counts['same_day']['project_task_count'] = 0;
            $task_age_counts['same_day']['catalog_task_count'] = 0;
            $task_age_counts['same_day']['incident_count'] = 0;
            $task_age_counts['same_day']['incident_task_count'] = 0;

            // cycle through each task
            foreach ($data as $task) {
                // get task created date
                $task_created_date = substr($task['sys_created_on'], 0, -9);

                /*
                * check task created date against time constraints, then check task
                * class name and increment the corresponding class name count
                */
                if ($two_months > $task_created_date) {
                    $task_age_counts['two_months']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['two_months']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['two_months']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['two_months']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['two_months']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['two_months']['incident_task_count']++;
                    }
                } elseif ($four_weeks > $task_created_date) {
                    $task_age_counts['oneTotwo_months']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['oneTotwo_months']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['oneTotwo_months']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['oneTotwo_months']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['oneTotwo_months']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['oneTotwo_months']['incident_task_count']++;
                    }
                } elseif ($two_weeks > $task_created_date) {
                    $task_age_counts['twoTofour_weeks']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['twoTofour_weeks']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['twoTofour_weeks']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['twoTofour_weeks']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['twoTofour_weeks']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['twoTofour_weeks']['incident_task_count']++;
                    }
                } elseif ($seven_days > $task_created_date) {
                    $task_age_counts['oneTotwo_weeks']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['oneTotwo_weeks']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['oneTotwo_weeks']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['oneTotwo_weeks']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['oneTotwo_weeks']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['oneTotwo_weeks']['incident_task_count']++;
                    }
                } elseif ($five_days > $task_created_date) {
                    $task_age_counts['fiveToseven_days']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['fiveToseven_days']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['fiveToseven_days']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['fiveToseven_days']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['fiveToseven_days']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['fiveToseven_days']['incident_task_count']++;
                    }
                } elseif ($two_days > $task_created_date) {
                    $task_age_counts['twoTofive_days']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['twoTofive_days']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['twoTofive_days']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['twoTofive_days']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['twoTofive_days']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['twoTofive_days']['incident_task_count']++;
                    }
                } elseif ($today > $task_created_date) {
                    $task_age_counts['oneTotwo_days']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['oneTotwo_days']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['oneTotwo_days']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['oneTotwo_days']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['oneTotwo_days']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['oneTotwo_days']['incident_task_count']++;
                    }
                } elseif ($today == $task_created_date) {
                    $task_age_counts['same_day']['count']++;

                    if ($task['sys_class_name'] == 'Project') {
                        $task_age_counts['same_day']['project_count']++;
                    } elseif ($task['sys_class_name'] == 'Project Task') {
                        $task_age_counts['same_day']['project_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Catalog Task') {
                        $task_age_counts['same_day']['catalog_task_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident') {
                        $task_age_counts['same_day']['incident_count']++;
                    } elseif ($task['sys_class_name'] == 'Incident Task') {
                        $task_age_counts['same_day']['incident_task_count']++;
                    }
                }
            }

            $response = [
                'success'                   => true,
                'security_tasks_count'      => count($security_tasks),
                'idm_tasks_count'           => count($idm_tasks),
                'sap_roleauth_tasks_count'  => count($sap_roleauth_tasks),
                'total'                     => count($data),
                'task_age_counts'           => $task_age_counts,
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
