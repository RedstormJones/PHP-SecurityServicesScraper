<?php

namespace App\Console\Commands\Process;

use App\Cylance\CylanceDevice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCylanceDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cylancedevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new cylance device data and update model';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        /*************************************
         * [2] Process devices into database *
         *************************************/

        Log::info(PHP_EOL.'****************************************'.PHP_EOL.'* Starting Cylance devices processing! *'.PHP_EOL.'****************************************');

        $contents = file_get_contents(storage_path('app/collections/devices.json'));
        $cylance_devices = \Metaclassing\Utility::decodeJson($contents);

        $user_regex = '/\w+\\\(\w+\.\w+-*\w+)/';

        foreach ($cylance_devices as $device) {
            Log::info('processing Cylance device: '.$device['Name']);

            $user_hits = [];

            // format datetimes for updating device record
            $created_date = $this->stringToDate($device['Created']);
            $offline_date = $this->stringToDate($device['OfflineDate']);

            // extract user from last users text
            preg_match($user_regex, $device['LastUsersText'], $user_hits);
            if (isset($user_hits[1])) {
                $last_user = ucwords(strtolower($user_hits[1]), '.');
            } else {
                $last_user = '';
            }

            // update model if it exists, otherwise create it
            $device_model = CylanceDevice::updateOrCreate(
                ['device_id'            => $device['DeviceId']],
                ['device_name'          => $device['Name']],
                ['zones_text'           => $device['ZonesText']],
                ['files_unsafe'         => $device['Unsafe']],
                ['files_quarantined'    => $device['Quarantined']],
                ['files_abnormal'       => $device['Abnormal']],
                ['files_waived'         => $device['Waived']],
                ['files_analyzed'       => $device['FilesAnalyzed']],
                ['agent_version_text'   => $device['AgentVersionText']],
                ['last_users_text'      => $last_user],
                ['os_versions_text'     => $device['OSVersionsText']],
                ['ip_addresses_text'    => $device['IPAddressesText']],
                ['mac_addresses_text'   => $device['MacAddressesText']],
                ['policy_name'          => $device['PolicyName']],
                ['device_created_at'    => $created_date],
                ['device_offline_date'  => $offline_date],
                ['data'                 => \Metaclassing\Utility::encodeJson($device)]
            );

            // touch device model to update 'updated_at' timestamp (in case nothing was changed)
            $device_model->touch();

            /*
            $exists = CylanceDevice::where('device_id', $device['DeviceId'])->value('id');

            $user_hits = [];

            // format datetimes for updating device record
            $created_date = $this->stringToDate($device['Created']);
            $offline_date = $this->stringToDate($device['OfflineDate']);

            // extract user from last users text
            preg_match($user_regex, $device['LastUsersText'], $user_hits);
            if (isset($user_hits[1])) {
                $last_user = ucwords(strtolower($user_hits[1]), '.');
            } else {
                $last_user = '';
            }

            // if the device record exists then update it, otherwise create a new one
            if ($exists) {
                $devicemodel = CylanceDevice::findOrFail($exists);

                $devicemodel->update([
                    'device_name'          => $device['Name'],
                    'zones_text'           => $device['ZonesText'],
                    'files_unsafe'         => $device['Unsafe'],
                    'files_quarantined'    => $device['Quarantined'],
                    'files_abnormal'       => $device['Abnormal'],
                    'files_waived'         => $device['Waived'],
                    'files_analyzed'       => $device['FilesAnalyzed'],
                    'agent_version_text'   => $device['AgentVersionText'],
                    'last_users_text'      => $last_user,
                    'os_versions_text'     => $device['OSVersionsText'],
                    'ip_addresses_text'    => $device['IPAddressesText'],
                    'mac_addresses_text'   => $device['MacAddressesText'],
                    'policy_name'          => $device['PolicyName'],
                    'device_created_at'    => $created_date,
                    'device_offline_date'  => $offline_date,
                    'data'                 => json_encode($device),
                ]);

                $devicemodel->save();

                // touch device model to update 'updated_at' timestamp (in case nothing was changed)
                $devicemodel->touch();

                Log::info('updated device: '.$device['Name']);
            } else {
                Log::info('creating device: '.$device['Name']);

                $new_device = new CylanceDevice();

                $new_device->device_id = $device['DeviceId'];
                $new_device->device_name = $device['Name'];
                $new_device->zones_text = $device['ZonesText'];
                $new_device->files_unsafe = $device['Unsafe'];
                $new_device->files_quarantined = $device['Quarantined'];
                $new_device->files_abnormal = $device['Abnormal'];
                $new_device->files_waived = $device['Waived'];
                $new_device->files_analyzed = $device['FilesAnalyzed'];
                $new_device->agent_version_text = $device['AgentVersionText'];
                $new_device->last_users_text = $last_user;
                $new_device->os_versions_text = $device['OSVersionsText'];
                $new_device->ip_addresses_text = $device['IPAddressesText'];
                $new_device->mac_addresses_text = $device['MacAddressesText'];
                $new_device->policy_name = $device['PolicyName'];
                $new_device->device_created_at = $created_date;
                $new_device->device_offline_date = $offline_date;
                $new_device->data = json_encode($device);

                $new_device->save();
            }
            */
        }

        // process soft deletes for old records
        $this->processDeletes();

        Log::info('* Cylance devices completed! *'.PHP_EOL);
    }

    /**
     * Delete old CylanceDevice models.
     *
     * @return void
     */
    public function processDeletes()
    {
        // create new datetime object and subtract one day to get delete_date
        $delete_date = Carbon::now()->subHours(2);

        // get all the devices
        $devices = CylanceDevice::all();

        /*
        * For each device, get its updated_at timestamp, remove the time of day portion, and check
        * it against delete_date to determine if its a stale record or not. If yes, delete it.
        **/
        foreach ($devices as $device) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $device->updated_at);

            // if updated_at is less than or equal to delete_date then we soft delete the device
            if ($updated_at->lt($delete_date)) {
                Log::info('deleting device: '.$device->device_name);
                $device->delete();
            }
        }
    }

    /**
     * Function to convert string timestamps to datetimes.
     *
     * @return string
     */
    public function stringToDate($date_str)
    {
        if ($date_str != null) {
            $date_regex = '/\/Date\((\d+)\)\//';
            preg_match($date_regex, $date_str, $date_hits);

            $datetime = Carbon::createFromTimestamp(intval($date_hits[1]) / 1000)->toDateTimeString();
        } else {
            $datetime = null;
        }

        return $datetime;
    }
}
