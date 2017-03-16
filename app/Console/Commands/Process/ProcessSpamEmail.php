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

        $time_added_regex = '/(.+) \(.+\)/';

        foreach ($spam_emails as $spam) {
            $reasons = '';
            $time_added_hits = [];

            // cycle through reasons
            foreach ($spam['reason'] as $reason) {
                // grab policy array and convert to a ';' separated string
                $policy_arr = $reason[1];
                $reason_str = implode('; ', $policy_arr);
                // appeand to reasons string
                $reasons .= $reason_str.'; ';
            }

            // normalize time added date
            if (preg_match($time_added_regex, $spam['time_added'], $time_added_hits))
            {
                $time_added = Carbon::createFromFormat('d M Y H:i', $time_added_hits[1])->toDateTimeString();
            }
            else {
                $time_added = '';
            }

            // convert quarantine names and recipients arrays to strings
            $quarantines = implode('; ', $spam['quarantine_names']);
            $recipients = implode('; ', $spam['recipients']);

            Log::info('processing spam record for: '.$spam['sender']);

            $spam_model = IronPortSpamEmail::updateOrCreate(
                [
                    'mid'                 => $spam['mid'],
                ],
                [
                    'subject'             => $spam['subject'],
                    'size'                => $spam['size'],
                    'quarantine_names'    => $quarantines,
                    'time_added'          => $time_added,
                    'reason'              => $reasons,
                    'recipients'          => $recipients,
                    'sender'              => $spam['sender'],
                    'esa_id'              => $spam['esa_id'],
                    'data'                => \Metaclassing\Utility::encodeJson($spam),
                ]
            );

            // touch spam model to update the "updated_at" timestamp in case nothing was changed
            $spam_model->touch();
        }

        $this->processDeletes();

        Log::info('* Completed IronPort spam emails! *');
    }

    /**
     * Function to process softdeletes for spam email.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subMonths(3);
        Log::info('spam delete date: '.$delete_date);

        $spam_emails = IronPortSpamEmail::get();

        foreach ($spam_emails as $spam) {
            $updated_at = Carbon::createFromFormat('Y-m-d H:i:s', $spam->updated_at);

            if ($updated_at->lt($delete_date)) {
                Log::info('deleting spam record: '.$spam->id);
                $spam->delete();
            }
        }
    }
}    // end of ProcessSpamEmail command class
