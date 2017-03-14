<?php

namespace App\Console\Commands\Process;

use App\IronPort\IronPortSpamEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSpamEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:spamemail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new spam email data and update model';

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
        /*
         * [2] Process spam emails into database
         */

        Log::info(PHP_EOL.'***********************************'.PHP_EOL.'* Starting spam email processing! *'.PHP_EOL.'***********************************');

        $contents = file_get_contents(storage_path('app/collections/spam.json'));
        $spam_emails = \Metaclassing\Utility::decodeJson($contents);

        foreach ($spam_emails as $spam) {
            $reasons = '';

            // cycle through reasons
            foreach ($spam['reason'] as $reason) {
                // grab policy array and convert to a ';' separated string
                $policy_arr = $reason[1];
                $reason_str = implode('; ', $policy_arr);
                // appeand to reasons string
                $reasons .= $reason_str.'; ';
            }

            // strip crap from time_added
            $timeadded = substr($spam['time_added'], 0, -13);

            // we need to check that timeadded is formatted correctly and, if not,
            // append 0's for either the seconds or for both the seconds and minutes
            if (!strstr($timeadded, ':')) {
                if (strlen($timeadded) == 11) {
                    $timeadded .= '00:00';
                } else {
                    $timeadded .= ':00';
                }
            } else {
                $timeadded = str_pad($timeadded, 17, '0');
            }

            // now we can use timeadded to create a datetime object
            $date = \DateTime::createFromFormat('d M Y H:i', $timeadded);
            $datetime = $date->format('Y-m-d H:i');

            // convert quarantine names and recipients arrays to strings
            $quarantines = implode('; ', $spam['quarantine_names']);
            $recipients = implode('; ', $spam['recipients']);

            $spam_model = IronPortSpamEmail::updateOrCreate(
                ['mid'                 => $spam['mid']],
                ['subject'             => $spam['subject']],
                ['size'                => $spam['size']],
                ['quarantine_names'    => $quarantines],
                ['time_added'          => $datetime],
                ['reason'              => $reasons],
                ['recipients'          => $recipients],
                ['sender'              => $spam['sender']],
                ['esa_id'              => $spam['esa_id']],
                ['data'                => \Metaclassing\Utility::encodeJson($spam)]
            );

            // touch spam model to update the "updated_at" timestamp in case nothing was changed
            $spam_model->touch();
        }

        $this->processDeletes();
    }

    /**
     * Function to process softdeletes for spam email.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subMonths(6);
        Log::info('spam delete date: '.$delete_date);

        $spam_emails = IronPortSpamEmail::all();

        foreach ($spam_emails as $spam) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $spam->updated_at);

            if ($updated_at->lt($delete_date))
            {
                Log::info('deleting spam record: '.$spam->id);
                $spam->delete();
            }
        }
    }
}    // end of ProcessSpamEmail command class
