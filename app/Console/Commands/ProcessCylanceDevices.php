<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Cylance\CylanceDevice;

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
     * @return mixed
     */
    public function handle()
    {
        $devices_content = file_get_contents(storage_path('logs/collections/devices.json'));
        $devices = json_decode($devices_content);

        foreach($devices as $device)
		{
            $updated = CylanceDevice::where('device_id', $device->DeviceId)->update([
				'device_name'		=> $device->Name,
				'zones_text'		=> $device->ZonesText,
				'files_unsafe'		=> $device->Unsafe,
				'files_quarantined'	=> $device->Quarantined,
				'files_abnormal'	=> $device->Abnormal,
				'files_waived'		=> $device->Waived,
				'files_analyzed'	=> $device->FilesAnalyzed,
				'agent_version_text'=> $device->AgentVersionText,
				'last_users_text'	=> $device->LastUsersText,
				'os_versions_text'	=> $device->OSVersionsText,
				'ip_addresses_text'	=> $device->IPAddressesText,
				'mac_addresses_text'=> $device->MacAddressesText,
				'policy_name' 		=> $device->PolicyName,
				'data' 				=> json_encode($device)
			]);

            if(!$updated)
            {
				echo 'creating device: '.$device->Name.PHP_EOL;
            	$this->createDevice($device);
        	}
			else
			{
				echo 'updated device: '.$device->Name.PHP_EOL;
			}
		}

		$this->processDeletes();
    }


    public function createDevice($device)
    {
    	$new_device = new CylanceDevice;

        $new_device->device_id = $device->DeviceId;
        $new_device->device_name = $device->Name;
        $new_device->zones_text = $device->ZonesText;
        $new_device->files_unsafe = $device->Unsafe;
        $new_device->files_quarantined = $device->Quarantined;
        $new_device->files_abnormal = $device->Abnormal;
        $new_device->files_waived = $device->Waived;
        $new_device->files_analyzed = $device->FilesAnalyzed;
        $new_device->agent_version_text = $device->AgentVersionText;
        $new_device->last_users_text = $device->LastUsersText;
        $new_device->os_versions_text = $device->OSVersionsText;
        $new_device->ip_addresses_text = $device->IPAddressesText;
        $new_device->mac_addresses_text = $device->MacAddressesText;
        $new_device->policy_name = $device->PolicyName;
        $new_device->data = json_encode($device);

        $new_device->save();
	}


    public function processDeletes()
    {
    	$today = new \DateTime('now');
		$yesterday = $today->modify('-1 day');
		$delete_date = $yesterday->format('Y-m-d H:i:s');

		$devices = CylanceDevice::where('updated_at', '<=', $delete_date)->get();

        foreach($devices as $device)
        {
			echo 'deleting device: '.$device->device_name.PHP_EOL;
			$device->delete();
        }
	}

} // end of ProcessCylanceDevices command class
