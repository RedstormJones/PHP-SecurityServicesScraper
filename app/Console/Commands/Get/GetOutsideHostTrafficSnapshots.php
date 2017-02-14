<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\Lancope\OutsideHostTrafficSnapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetOutsideHostTrafficSnapshots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:outsidehosttrafficsnapshots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new outside host traffic snapshots';

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
         * [1] Get outside host traffic snapshots
         */

        Log::info(PHP_EOL.PHP_EOL.'****************************************************'.PHP_EOL.'* Starting outside host traffic snapshots crawler! *'.PHP_EOL.'****************************************************');

        // setup cookiejar and domain id
        $cookiejar = storage_path('app/cookies/smc_cookie.txt');
        $domainID = 123;

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url at authentication service
        $url = getenv('LANCOPE_URL').'/smc/j_spring_security_check';

        // put authentication data together
        $post = [
            'j_username'    => getenv('LANCOPE_USERNAME'),
            'j_password'    => getenv('LANCOPE_PASSWORD'),
        ];

        // post authentication data to service, capture response and dump to file
        $response = $crawler->post($url, '', $this->postArrayToString($post));
        file_put_contents(storage_path('app/responses/smc.auth.dump'), $response);

        // point url to dashboard to get app traffic snapshots for default hostgroup
        $url = getenv('LANCOPE_URL').'/smc/rest/domains/123/hostgroups/0/applicationTraffic';

        // send request, capture response and dump it to file
        $response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/outsidehost_apptraffic_dump.json'), $response);

        // JSON decode response into an array
        $response_arr = \Metaclassing\Utility::decodeJson($response);

        // instantiate collection array and setup regexes
        $outsidehost_apptraffic_collection = [];
        $trim_regex = '/(.+)\..+/';
        $replace_regex = '/T/';

        // cycle through response array
        foreach ($response_arr as $response) {
            // grab the data we care about and the time period value
            $app_dashboard = $response['applicationTrafficPerApplication'];
            $timePeriod = $response['timePeriod'];

            // format time period value to Y-m-d H:i:s
            preg_match($trim_regex, $timePeriod, $hits);
            $time_period = preg_replace($replace_regex, ' ', $hits[1]);

            // cycle through data and build collections array
            foreach ($app_dashboard as $app) {
                $app['timePeriod'] = $time_period;
                $outsidehost_apptraffic_collection[] = $app;
            }
        }

        Log::info('outside host traffic snapshots count: '.count($outsidehost_apptraffic_collection));

        // JSON encode and dump collection to file
        file_put_contents(storage_path('app/collections/outsidehost_apptraffic.json'), \Metaclassing\Utility::encodeJson($outsidehost_apptraffic_collection));

        /*
         * [2] Process outside host traffic snapshots into database
         */

        Log::info(PHP_EOL.'*******************************************************'.PHP_EOL.'* Starting outside host traffic snapshots processing! *'.PHP_EOL.'*******************************************************');

        foreach ($outsidehost_apptraffic_collection as $app) {
            Log::info('creating new record for '.$app['applicationName'].' during '.$app['timePeriod']);

            $snapshot = new OutsideHostTrafficSnapshot();

            $snapshot->application_id = $app['applicationId'];
            $snapshot->application_name = $app['applicationName'];
            $snapshot->time_period = $app['timePeriod'];
            $snapshot->traffic_outbound_Bps = $app['trafficOutboundBps'];
            $snapshot->traffic_inbound_Bps = $app['trafficInboundBps'];
            $snapshot->traffic_within_Bps = $app['trafficWithinBps'];
            $snapshot->data = \Metaclassing\Utility::encodeJson($app);

            $snapshot->save();
        }

        $this->processDeletes();

        Log::info('* Completed outside host traffic snapshots! *');
    }

    /**
     * Function to convert post information from an assoc array to a string.
     *
     * @return string
     */
    public function postArrayToString($post)
    {
        $postarray = [];
        foreach ($post as $key => $value) {
            $postarray[] = $key.'='.$value;
        }

        // takes the postarray array and concatenates together the values with &'s
        $poststring = implode('&', $postarray);

        return $poststring;
    }

    /**
     * Function to process softdeletes on application traffic snapshots.
     *
     * @return void
     */
    public function processDeletes()
    {
        $delete_date = Carbon::now()->subDays(7)->toDateTimeString();
        Log::info('delete data: '.$delete_date);

        $apptraffic_snapshots = OutsideHostTrafficSnapshot::where('updated_at', '<', $delete_date)->get();

        foreach ($apptraffic_snapshots as $snapshot) {
            Log::info('deleting record for '.$snapshot->application_name.' during time period '.$snapshot->time_period);
            $snapshot->delete();
        }
    }
}
