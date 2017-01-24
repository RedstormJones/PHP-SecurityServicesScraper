<?php

namespace App\Console\Commands\Crawl;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;

class CrawlSiteSubnets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:sitesubnets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl netman to get site subnets';

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
        $cookiejar = storage_path('app/cookies/netman_cookie.txt');

        $crawler = new \Crawler\Crawler($cookiejar);

        $url = 'https:/'.'/netman/reports/site-subnet-report.php';

        $response = $crawler->get($url);
        file_put_contents(storage_path('app/responses/netman.response.dump'), $response);

        $site_subnets = explode(PHP_EOL, $response);

        echo 'received '.count($site_subnets).' site subnets'.PHP_EOL;

        $collection = [];

        foreach ($site_subnets as $site_subnet) {
            $data = [];

            $pieces = explode(',', $site_subnet);
            $ip_pieces = explode('/', $pieces[0]);

            echo 'found subnet for site: '.$pieces[1].PHP_EOL;

            $data['ip_prefix'] = trim($pieces[0]);
            $data['site'] = trim($pieces[1]);
            $data['ip_address'] = trim($ip_pieces[0]);
            $data['netmask'] = trim($ip_pieces[1]);

            $collection[] = $data;
        }

        echo 'count: '.count($collection).PHP_EOL;

        file_put_contents(storage_path('app/collections/subnet_collection.json'), \Metaclassing\Utility::encodeJson($collection));
    }
}
