<?php

namespace App\Console\Commands\Process;

use App\Cylance\CylanceThreat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        Log::info(PHP_EOL.'****************************************'.PHP_EOL.'* Starting Cylance threats processing! *'.PHP_EOL.'****************************************');

        $contents = file_get_contents(storage_path('app/collections/threats.json'));
        $cylance_threats = \Metaclassing\Utility::decodeJson($contents);

        foreach ($cylance_threats as $threat) {
            Log::info('processing Cylance threat: '.$threat['CommonName']);

            // format datetimes for threat record
            $first_found = $this->stringToDate($threat['FirstFound']);
            $last_found = $this->stringToDate($threat['LastFound']);
            $active_last_found = $this->stringToDate($threat['ActiveLastFound']);
            $allowed_last_found = $this->stringToDate($threat['AllowedLastFound']);
            $blocked_last_found = $this->stringToDate($threat['BlockedLastFound']);
            $cert_timestamp = $this->stringToDate($threat['CertTimeStamp']);

            $cylance_threat = CylanceThreat::withTrashed()->updateOrCreate(
                [
                    'threat_id'                => $threat['Id'],
                ],
                [
                    'common_name'              => $threat['CommonName'],
                    'cylance_score'            => $threat['CylanceScore'],
                    'active_in_devices'        => $threat['ActiveInDevices'],
                    'allowed_in_devices'       => $threat['AllowedInDevices'],
                    'blocked_in_devices'       => $threat['BlockedInDevices'],
                    'suspicious_in_devices'    => $threat['SuspiciousInDevices'],
                    'first_found'              => $first_found,
                    'last_found'               => $last_found,
                    'last_found_active'        => $active_last_found,
                    'last_found_allowed'       => $allowed_last_found,
                    'last_found_blocked'       => $blocked_last_found,
                    'md5'                      => $threat['MD5'],
                    'virustotal'               => $threat['VirusTotal'],
                    'is_virustotal_threat'     => $threat['IsVirusTotalThreat'],
                    'full_classification'      => $threat['FullClassification'],
                    'is_unique_to_cylance'     => $threat['IsUniqueToCylance'],
                    'is_safelisted'            => $threat['IsSafelisted'],
                    'detected_by'              => $threat['DetectedBy'],
                    'threat_priority'          => $threat['ThreatPriority'],
                    'current_model'            => $threat['CurrentModel'],
                    'priority'                 => $threat['Priority'],
                    'file_size'                => $threat['FileSize'],
                    'global_quarantined'       => $threat['IsGlobalQuarantined'],
                    'signed'                   => $threat['Signed'],
                    'cert_issuer'              => $threat['CertIssuer'],
                    'cert_publisher'           => $threat['CertPublisher'],
                    'cert_timestamp'           => $cert_timestamp,
                    'data'                     => \Metaclassing\Utility::encodeJson($threat),
                ]
            );

            // touch threat model to update the 'updated_at' timestamp (in case nothing was changed)
            $cylance_threat->touch();

            // restore threat model to remove deleted_at timestamp
            $cylance_threat->restore();
        }

        // process soft deletes for old records
        $this->processDeletes();

        Log::info('* Cylance threats completed! *'.PHP_EOL);
    }

    /**
     * Function to soft delete expired threat records.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subHours(2);

        $threats = CylanceThreat::all();

        foreach ($threats as $threat) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $threat->updated_at);

            if ($updated_at->lt($delete_date)) {
                Log::info('deleting threat: '.$threat->common_name);
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

            $datetime = Carbon::createFromTimestamp(intval($date_hits[1]) / 1000)->toDateTimeString();
        } else {
            $datetime = null;
        }

        return $datetime;
    }
} // end of ProcessCylanceThreats command class
