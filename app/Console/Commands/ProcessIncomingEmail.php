<?php

namespace App\Console\Commands;

use App\IronPort\IncomingEmail;
use Illuminate\Console\Command;

class ProcessIncomingEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:incomingemail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new incoming email data and update model';

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
        $contents = file_get_contents(storage_path('app/collections/incoming_email.json'));
        $incomingemails = \Metaclassing\Utility::decodeJson($contents);

        foreach ($incomingemails as $email) {
            $begindate = rtrim($email['Begin Date'], ' GMT');
            $enddate = rtrim($email['End Date'], ' GMT');

            echo 'creating email record for: '.$email['Sender Domain'].PHP_EOL;
            $new_email = new IncomingEmail();

            $new_email->begin_date = $begindate;
            $new_email->end_date = $enddate;
            $new_email->sender_domain = $email['Sender Domain'];
            $new_email->connections_rejected = $email['Connections Rejected'];
            $new_email->connections_accepted = $email['Connections Accepted'];
            $new_email->total_attempted = $email['Total Attempted'];
            $new_email->stopped_by_recipient_throttling = $email['Stopped by Recipient Throttling'];
            $new_email->stopped_by_reputation_filtering = $email['Stopped by Reputation Filtering'];
            $new_email->stopped_by_content_filter = $email['Stopped by Content Filter'];
            $new_email->stopped_as_invalid_recipients = $email['Stopped as Invalid Recipients'];
            $new_email->spam_detected = $email['Spam Detected'];
            $new_email->virus_detected = $email['Virus Detected'];
            $new_email->amp_detected = $email['Detected by Advanced Malware Protection'];
            $new_email->total_threats = $email['Total Threat'];
            $new_email->marketing = $email['Marketing'];
            $new_email->social = $email['Social'];
            $new_email->bulk = $email['Bulk'];
            $new_email->total_graymails = $email['Total Graymails'];
            $new_email->clean = $email['Clean'];
            $new_email->data = json_encode($email);

            $new_email->save();
        }

        //$this->processDeletes();
    }

    /**
     * Function to process softdeletes for incoming email.
     *
     * @return void
     */
    public function processDeletes()
    {
        $today = new \DateTime('now');
        $yesterday = $today->modify('-1 day');
        $delete_date = $yesterday->format('Y-m-d H:i:s');

        $incomingemails = IncomingEmail::where('updated_at', '<=', $delete_date)->get();

        foreach ($incomingemails as $email) {
            echo 'deleting email: '.$email->id.PHP_EOL;
            $email->delete();
        }
    }
}    // end of ProcessIncomingEmail command class
