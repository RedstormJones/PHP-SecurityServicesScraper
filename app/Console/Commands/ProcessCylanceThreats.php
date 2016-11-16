<?php

namespace App\Console\Commands;

use App\Cylance\CylanceThreat;
use Illuminate\Console\Command;

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
        $threats_content = file_get_contents(storage_path('app/collections/threats.json'));
        $threats = json_decode($threats_content);

        foreach ($threats as $threat) {
            $exists = CylanceThreat::where('threat_id', $threat->Id)->whereNull('deleted_at')->value('id');

            if ($exists) {
                // format datetimes for updating threat record
                $first_found = $this->stringToDate($threat->FirstFound);
                $last_found = $this->stringToDate($threat->LastFound);
                $active_last_found = $this->stringToDate($threat->ActiveLastFound);
                $allowed_last_found = $this->stringToDate($threat->AllowedLastFound);
                $blocked_last_found = $this->stringToDate($threat->BlockedLastFound);

                $updated = CylanceThreat::where('id', $exists)->update([
                    'common_name'              => $threat->CommonName,
                    'cylance_score'            => $threat->CylanceScore,
                    'active_in_devices'        => $threat->ActiveInDevices,
                    'allowed_in_devices'       => $threat->AllowedInDevices,
                    'blocked_in_devices'       => $threat->BlockedInDevices,
                    'suspicious_in_devices'    => $threat->SuspiciousInDevices,
                    'first_found'              => $first_found,
                    'last_found'               => $last_found,
                    'last_found_active'        => $active_last_found,
                    'last_found_allowed'       => $allowed_last_found,
                    'last_found_blocked'       => $blocked_last_found,
                    'md5'                      => $threat->MD5,
                    'virustotal'               => $threat->VirusTotal,
                    'full_classification'      => $threat->FullClassification,
                    'is_unique_to_cylance'     => $threat->IsUniqueToCylance,
                    'detected_by'              => $threat->DetectedBy,
                    'threat_priority'          => $threat->ThreatPriority,
                    'current_model'            => $threat->CurrentModel,
                    'priority'                 => $threat->Priority,
                    'file_size'                => $threat->FileSize,
                    'global_quarantined'       => $threat->GlobalQuarantined,
                    'data'                     => json_encode($threat),
                ]);

                // touch threat model to update 'updated_at' timestamp (in case nothing was changed)
                $threatmodel = CylanceThreat::find($exists);
                $threatmodel->touch();

                echo 'updated threat: '.$threat->CommonName.PHP_EOL;
            } else {
                echo 'creating threat: '.$threat->CommonName.PHP_EOL;
                $this->createThreat($threat);
            }
        }

        // process soft deletes for old records
        $this->processDeletes();
    }

    /**
     * Function to create a new Cylance Threat model.
     *
     * @return void
     */
    public function createThreat($threat)
    {
        // format datetimes for new threat record
        $first_found = $this->stringToDate($threat->FirstFound);
        $last_found = $this->stringToDate($threat->LastFound);
        $active_last_found = $this->stringToDate($threat->ActiveLastFound);
        $allowed_last_found = $this->stringToDate($threat->AllowedLastFound);
        $blocked_last_found = $this->stringToDate($threat->BlockedLastFound);

        // create new Cylance threat record and assign values
        $new_threat = new CylanceThreat();

        $new_threat->threat_id = $threat->Id;
        $new_threat->common_name = $threat->CommonName;
        $new_threat->cylance_score = $threat->CylanceScore;
        $new_threat->active_in_devices = $threat->ActiveInDevices;
        $new_threat->allowed_in_devices = $threat->AllowedInDevices;
        $new_threat->blocked_in_devices = $threat->BlockedInDevices;
        $new_threat->suspicious_in_devices = $threat->SuspiciousInDevices;
        $new_threat->first_found = $first_found;
        $new_threat->last_found = $last_found;
        $new_threat->last_found_active = $active_last_found;
        $new_threat->last_found_allowed = $allowed_last_found;
        $new_threat->last_found_blocked = $blocked_last_found;
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

    /**
     * Function to soft delete expired threat records.
     *
     * @return void
     */
    public function processDeletes()
    {
        $today = new \DateTime('now');
        $yesterday = $today->modify('-1 day');
        $delete_date = $yesterday->format('Y-m-d');

        //$threats = CylanceThreat::where('updated_at', '<=', $delete_date)->get();
        $threats = CylanceThreat::all();

        foreach ($threats as $threat) {
            $updated_at = substr($threat->updated_at, 0, -9);

            if ($updated_at <= $delete_date) {
                echo 'deleting threat: '.$threat->common_name.PHP_EOL;
                $threat->delete();
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
            $datetime = date('Y-m-d H:i:s', (intval($date_hits[1]) / 1000));
        } else {
            $datetime = null;
        }

        return $datetime;
    }
} // end of ProcessCylanceThreats command class
