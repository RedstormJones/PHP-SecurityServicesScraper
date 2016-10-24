<?php

namespace App\Console\Commands;

use App\Netman\SiteSubnet;
use Illuminate\Console\Command;

class ProcessSiteSubnets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:sitesubnets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new job site subnet data and update model';

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
        $site_subnets = SiteSubnet::all();

        if(!$site_subnets->isEmpty())
        {
            foreach($site_subnets as $site_subnet)
            {
                echo 'deleting record for '.$site_subnet->site.' with id '.$site_subnet->id.PHP_EOL;
                $site_subnet->delete();
            }
        }
        else
        {
            echo 'site subnet collection came back empty'.PHP_EOL;
        }

        $contents = file_get_contents(storage_path('app/collections/subnet_collection.json'));
        $new_site_subnets = \Metaclassing\Utility::decodeJson($contents);

        foreach($new_site_subnets as $site_subnet)
        {
            echo 'creating new record for '.$site_subnet['site'].' with prefix of '.$site_subnet['ip_prefix'].PHP_EOL;
            $site = new SiteSubnet();

            $site->ip_prefix = $site_subnet['ip_prefix'];
            $site->site = $site_subnet['site'];
            $site->ip_address = $site_subnet['ip_address'];
            $site->netmask = $site_subnet['netmask'];
            $site->data = \Metaclassing\Utility::encodeJson($site_subnet);

            $site->save();
        }

    }
}
