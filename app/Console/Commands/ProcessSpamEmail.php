<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\IronPort\IronPortSpamEmail;

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
		$contents = file_get_contents(storage_path('logs/collections/spam.json'));
		$spam_emails = \Metaclassing\Utility::decodeJson($contents);

		foreach($spam_emails as $spam)
		{
		    $reasons = '';
		    // cycle through reasons
		    foreach($spam['reason'] as $reason)
		    {
		        // grab policy array and convert to a ';' separated string
		        $policy_arr = $reason[1];
        		$reason_str = implode("; ", $policy_arr);
		        // appeand to reasons string
        		$reasons .= $reason_str.'; ';
		    }

		    // strip crap from time_added
		    $timeadded = rtrim((string)$spam['time_added'], ' (GMT -05:00)');

			// we need to check that timeadded is formatted correctly and, if not,
			// append 0's for either the seconds or for both the seconds and minutes
		    if(!strstr($timeadded, ':')) {
		        if(strlen($timeadded) == 11) {
        		    $timeadded .= '00:00';
		        }
        		else {
		            $timeadded .= ':00';
        		}
		    }
		    else {
        		$timeadded = str_pad($timeadded, 17, '0');
		    }

			// now we can use timeadded to create a datetime object
		    $date = \DateTime::createFromFormat('d M Y H:i', $timeadded);
		    $datetime = $date->format('Y-m-d H:i');

			// convert quarantine names and recipients arrays to strings
		    $quarantines = implode("; ", $spam['quarantine_names']);
		    $recipients = implode("; ", $spam['recipients']);

			// attempt to find existing record and update
			$updated = IronPortSpamEmail::where('mid', $spam['mid'])->update([
				'mid'				=> $spam['mid'],
				'subject'			=> $spam['subject'],
				'size'				=> $spam['size'],
				'quarantine_names'	=> $quarantines,
				'time_added'		=> $datetime,
				'reason'			=> $reasons,
				'recipients'		=> $recipients,
				'sender'			=> $spam['sender'],
				'esa_id'			=> $spam['esa_id'],
				'data'				=> \Metaclassing\Utility::encodeJson($spam)
			]);

			// if not updated then create new
			if(!$updated)
			{
				echo 'creating spam record for: '.$spam['sender'].PHP_EOL;
				$new_spam = new IronPortSpamEmail;

				$new_spam->mid = $spam['mid'];
				$new_spam->subject = $spam['subject'];
				$new_spam->size = $spam['size'];
				$new_spam->quarantine_names = $quarantines;
				$new_spam->time_added = $datetime;
				$new_spam->reason = $reasons;
				$new_spam->recipients = $recipients;
				$new_spam->sender = $spam['sender'];
				$new_spam->esa_id = $spam['esa_id'];
				$new_spam->data = \Metaclassing\Utility::encodeJson($spam);

				$new_spam->save();
			}
			else
			{
				echo 'updated spam record for: '.$spam['sender'].PHP_EOL;
			}
		}

		//$this->processDeletes();
    }

	/**
	* Function to process softdeletes for spam email
	*
	* @return void
	*/
	public function processDeletes()
	{
		$today = new \DateTime('now');
		$pastdate = $today->modify('-3 month');
		$delete_date = $pastdate->format('Y-m-d H:i:s');

		$spam_emails = IronPortSpamEmail::where('updated_at', '<=', $delete_date)->get();

		foreach($spam_emails as $spam)
		{
			echo 'deleting spam record: '.$spam->id.PHP_EOL;
			$spam->delete();
		}
	}

}	// end of ProcessSpamEmail command class
