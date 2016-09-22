<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Cylance\CylanceThreat;

class ProcessCylanceThreats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cylancethreats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new Cylance threat data and update the model';

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
		$threats_content = file_get_contents(getenv('COLLECTIONS').'threats.json');
		$threats = json_decode($threats_content);

		foreach($threats as $threat)
		{
			$updated = CylanceThreat::where('threat_id', $threat->Id)->update([
				'common_name'			=> $threat->CommonName,
				'cylance_score'			=> $threat->CylanceScore,
				'active_in_devices'		=> $threat->ActiveInDevices,
				'allowed_in_devices'	=> $threat->AllowedInDevices,
				'blocked_in_devices'	=> $threat->BlockedInDevices,
				'suspicious_in_devices'	=> $threat->SuspiciousInDevices,
				'md5'					=> $threat->MD5,
				'virustotal'			=> $threat->VirusTotal,
				'full_classification'	=> $threat->FullClassification,
				'is_unique_to_cylance'	=> $threat->IsUniqueToCylance,
				'detected_by'			=> $threat->DetectedBy,
				'threat_priority'		=> $threat->ThreatPriority,
				'current_model'			=> $threat->CurrentModel,
				'priority'				=> $threat->Priority,
				'file_size'				=> $threat->FileSize,
				'global_quarantined'	=> $threat->GlobalQuarantined,
				'data'					=> json_encode($threat)
			]);

			if(!$updated)
			{
				echo 'creating threat: '.$threat->CommonName.PHP_EOL;
				$this->createThreat($threat);
			}
			else
			{
				echo 'updated threat: '.$threat->CommonName.PHP_EOL;
			}
		}

		$this->processDeletes();
    }

	public function createThreat($threat)
	{
		$new_threat = new CylanceThreat;

		$new_threat->threat_id = $threat->Id;
		$new_threat->common_name = $threat->CommonName;
		$new_threat->cylance_score = $threat->CylanceScore;
		$new_threat->active_in_devices = $threat->ActiveInDevices;
		$new_threat->allowed_in_devices = $threat->AllowedInDevices;
		$new_threat->blocked_in_devices = $threat->BlockedInDevices;
		$new_threat->suspicious_in_devices = $threat->SuspiciousInDevices;
		$new_threat->md5 = $threat->MD5;
		$new_threat->virustotal = $threat->VirusTotal;
		$new_threat->full_classification = $threat->FullClassification;
		$new_threat->is_unique_to_cylance = $threat->IsUniqueToCylance;
		$new_threat->detected_by = $threat->DetectedBy;
		$new_threat->threat_priority = $threat->ThreatPriority;
		$new_threat->current_model = $threat->CurrentModel;
		$new_threat->priority = $threat->Priority;
		$new_threat->file_size = $threat->FileSize;
		$new_threat->global_quarantined = $threat->GlobalQuarantined;
		$new_threat->data = json_encode($threat);

		$new_threat->save();
	}

	public function processDeletes()
	{
		$today = new \DateTime('now');
		$yesterday = $today->modify('-1 day');
		$delete_date = $yesterday->format('Y-m-d H:i:s');

		$threats = CylanceThreat::where('updated_at', '<=', $delete_date)->get();

		foreach($threats as $threat)
		{
			echo 'deleting threat: '.$threat->common_name.PHP_EOL;
			$threat->delete();
		}
	}

} // end of ProcessCylanceThreats command class
