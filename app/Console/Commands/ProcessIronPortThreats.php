<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\IronPort\IronPortThreat;

class ProcessIronPortThreats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:ironportthreats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new IronPort threat data and update model';

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
		$contents = file_get_contents(getenv('COLLECTIONS').'threat_details.json');
		$threatdetails = \Metaclassing\Utility::decodeJson($contents);

		foreach($threatdetails as $threat)
		{
		    $begindate = rtrim($threat['Begin Date'], ' GMT');
		    $enddate = rtrim($threat['End Date'], ' GMT');

			$updated = IronPortThreat::where('begin_date', $begindate)->update([
				'category'	=> $threat['Category'],
				'threat_type'	=> $threat['Threat Name'],
				'total_messages'=> $threat['Total Messages'],
				'data'			=> \Metaclassing\Utility::encodeJson($threat)
			]);

			if(!$updated)
			{
				echo 'creating threat record for: '.$threat['Threat Name'].PHP_EOL;
				$new_threat = new IronPortThreat;

				$new_threat->begin_date = $begindate;
				$new_threat->end_date = $enddate;
				$new_threat->category = $threat['Category'];
				$new_threat->threat_type = $threat['Threat Name'];
				$new_threat->total_messages = $threat['Total Messages'];
				$new_threat->data = \Metaclassing\Utility::encodeJson($threat);

				$new_threat->save();
			}
			else
			{
				echo 'updated threat record for: '.$threat['Threat Name'].PHP_EOL;
			}
		}
	}

}	// end of ProcessIronPortThreats command class
