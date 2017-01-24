<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use App\Netman\SiteSubnet;
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

        foreach ($site_subnets as $site_subnet) {
            $data = [];

            $pieces = explode(',', $site_subnet);
            $ip_pieces = explode('/', $pieces[0]);

            $data['ip_prefix'] = trim($pieces[0]);
            $data['site'] = trim($pieces[1]);
            $data['ip_address'] = trim($ip_pieces[0]);
            $data['netmask'] = trim($ip_pieces[1]);

            $collection[] = $data;
        }

        Log::info('Netman site subnets count: '.count($collection));

        file_put_contents(storage_path('app/collections/subnet_collection.json'), \Metaclassing\Utility::encodeJson($collection));

        /*
         * [2] Process site subnet records into database
         */

        Log::info(PHP_EOL.'************************************'.PHP_EOL.'* Starting site subnet processing! *'.PHP_EOL.'************************************');

        // get rid of existing site subnet records
        $this->processDeletes();

        foreach ($collection as $site_subnet) {
            Log::info('creating new site subnet record for '.$site_subnet['site'].' with prefix of '.$site_subnet['ip_prefix']);

            $site = new SiteSubnet();

            $site->ip_prefix = $site_subnet['ip_prefix'];
            $site->site = $site_subnet['site'];
            $site->ip_address = $site_subnet['ip_address'];
            $site->netmask = $site_subnet['netmask'];
            $site->data = \Metaclassing\Utility::encodeJson($site_subnet);

            $site->save();
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
