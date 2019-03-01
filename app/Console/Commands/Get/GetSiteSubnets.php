<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\Netman\SiteSubnet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetSiteSubnets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:sitesubnets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new site subnet data from Netman';

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
         * [1] Get site subnets
         */

        Log::info(PHP_EOL.PHP_EOL.'*********************************'.PHP_EOL.'* Starting site subnet crawler! *'.PHP_EOL.'*********************************');

        $cookiejar = storage_path('app/cookies/netman_cookie.txt');

        $crawler = new \Crawler\Crawler($cookiejar);

        $url = 'https:/'.'/netman/reports/site-subnet-report.php';

        $response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/netman.response.dump'), $response);

        $site_subnets = explode(PHP_EOL, $response);

        $collection = [];

        foreach ($site_subnets as $site) {
            $date_added = str_replace(' ', 'T', Carbon::now());

            $pieces = explode(',', $site);
            $ip_pieces = explode('/', $pieces[0]);

            $collection[] = [
                'date_added'    => $date_added,
                'ip_prefix'     => trim($pieces[0]),
                'site'          => trim($pieces[1]),
                'ip_address'    => trim($ip_pieces[0]),
                'netmask'       => trim($ip_pieces[1]),
            ];
        }

        Log::info('Netman site subnets count: '.count($collection));

        file_put_contents(storage_path('app/collections/subnet_collection.json'), \Metaclassing\Utility::encodeJson($collection));

        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));
        $producer = new \Kafka\Producer();

        foreach ($collection as $site_subnet) {
            $result = $producer->send([
                [
                    'topic' => 'site_subnets',
                    'value' => \Metaclassing\Utility::encodeJson($site_subnet),
                ],
            ]);

            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[*] Data successfully sent to Kafka: '.$site_subnet['site']);
            }
        }

        Log::info('* Completed Netman site subnets! *');
    }

    /**
     * Delete existing site subnet records if any exist.
     *
     * @return void
     */
    public function processDeletes()
    {
        $site_subnets = SiteSubnet::all();

        if (!$site_subnets->isEmpty()) {
            foreach ($site_subnets as $site_subnet) {
                Log::info('deleting site subnet for '.$site_subnet->site.' with id '.$site_subnet->id);
                $site_subnet->delete();
            }
        } else {
            Log::warning('site subnet collection came back empty');
        }
    }
}
