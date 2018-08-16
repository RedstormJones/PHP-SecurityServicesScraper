<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetNexposeResources extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:nexposeresources';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the list of resources available in Nexpose';

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
        Log::info(PHP_EOL.PHP_EOL.'***************************************'.PHP_EOL.'* Starting Nexpose Resources crawler! *'.PHP_EOL.'***************************************');

        // get creds and build auth string
        $username = getenv('NEXPOSE_USERNAME');
        $password = getenv('NEXPOSE_PASSWORD');

        $auth_str = base64_encode($username.':'.$password);

        // response path
        $response_path = storage_path('app/responses/');

        // cookie jar
        $cookiejar = storage_path('app/cookies/nexpose_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // set url
        $url = getenv('NEXPOSE_URL').'/assets?size=500&page=0';
        Log::info('[+] nexpose url: '.$url);

        // auth header
        $headers = [
            'Authorization: Basic '.$auth_str,
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        $collection = [];

        do {
            // send request and capture response
            Log::info('[+] sending GET request to: '.$url);
            $json_response = $crawler->get($url);

            // dump response to file
            file_put_contents($response_path.'nexpose_assets.response', $json_response);
            $response = \Metaclassing\Utility::decodeJson($json_response);

            $collection[] = $response['resources'];

            //$page_num = $response['page']['number'];
            //$total_pages = $response['page']['totalPages'];

            $links = $response['links'];

            foreach ($links as $link) {
                if ($link['rel'] == 'next') {
                    $url = $link['href'];
                    break;
                } else {
                    $url = null;
                }
            }
        } while ($url);

        // collapse collection down to simple array
        $assets_array = array_collapse($collection);

        $assets = [];

        Log::info('[+] starting data normalization...');

        // some data normalization
        foreach ($assets_array as $asset) {
            // downstream processing will throw errors if data already has an id field, so pull id and add it back as asset_id
            $id = array_pull($asset, 'id');
            $asset['asset_id'] = $id;

            // deal with the services array
            if (array_key_exists('services', $asset)) {
                // pull services array from asset
                $services = array_pull($asset, 'services');

                // build new services array
                $services_array = [];
                foreach ($services as $service) {
                    // drop the service links array
                    array_forget($service, 'links');

                    // deal with service configurations array
                    if (array_key_exists('configurations', $service)) {
                        // pull the service configs array
                        $configs = array_pull($service, 'configurations');

                        // build new configs array
                        $configs_array = [];
                        foreach ($configs as $config) {
                            $regex = '/^((\w*\.)+.*)$/';

                            // if config name starts with a '.' and set config name to substr of itself but skip the '.'
                            if ($config['name'][0] == '.') {
                                $config['name'] = substr($config['name'], 1, strlen($config['name']));
                            }

                            // if config name is a pattern of ([string].)*[string] then replace all the periods with underscores
                            if (preg_match($regex, $config['name'], $hits)) {
                                $config['name'] = str_replace('.', '_', $config['name']);
                            }

                            if (array_key_exists('value', $config)) {
                                $configs_array[$config['name']] = $config['value'];
                            } else {
                                $configs_array[$config['name']] = '';
                            }
                        }

                        // add service configs array back to service
                        $service['configurations'] = $configs_array;
                    }

                    // deal with service databases array
                    if (array_key_exists('databases', $service)) {
                        // pull the databases array from service
                        $databases = array_pull($service, 'databases');

                        // build new databases array
                        $databases_array = [];
                        foreach ($databases as $database) {
                            $databases_array[] = $database['name'];
                        }

                        // add databases array back to service
                        $service['databases'] = $databases_array;
                    }

                    // deal with service userGroups array
                    if (array_key_exists('userGroups', $service)) {
                        // pull userGroups array from service
                        $usergroups = array_pull($service, 'userGroups');

                        // build new userGroups array
                        $usergroups_array = [];
                        foreach ($usergroups as $group) {
                            $usergroups_array[$group['id']] = $group['name'];
                        }

                        // add userGroups array back to service
                        $service['userGroups'] = $usergroups_array;
                    }

                    // deal with service users array
                    if (array_key_exists('users', $service)) {
                        // pull users array from service
                        $users = array_pull($service, 'users');

                        // build new users array
                        $users_array = [];
                        foreach ($users as $user) {
                            $users_array[$user['id']] = $user;
                        }

                        // add users array back to service
                        $service['users'] = $users_array;
                    }

                    $services_array[$service['port']] = $service;
                }

                // add services array back to asset
                $asset['services'] = $services_array;
            }

            // deal with history array
            if (array_key_exists('history', $asset)) {
                // pull history array from asset
                $history = array_pull($asset, 'history');

                // build new history array
                $history_array = [];
                foreach ($history as $h) {
                    $history_array[$h['version']] = $h;
                }

                // add history array back to asset
                $asset['history'] = $history_array;
            }

            // deal with ids array
            if (array_key_exists('ids', $asset)) {
                // pull ids array from asset
                $ids = array_pull($asset, 'ids');

                // build new ids array
                $ids_array = [];
                foreach ($ids as $id) {
                    $ids_array[$id['source']] = $id['id'];
                }

                // add ids array back to asset
                $asset['asset_ids'] = $ids_array;
            }

            // deal with configurations array
            if (array_key_exists('configurations', $asset)) {
                // pull configs array from asset
                $configs = array_pull($asset, 'configurations');

                // build new configs array
                $configs_array = [];
                foreach ($configs as $config) {
                    $configs_array[$config['name']] = $config['value'];
                }

                // add configs array back to asset
                $asset['configurations'] = $configs_array;
            }

            // deal with hostNames array
            if (array_key_exists('hostNames', $asset)) {
                // pull hostNames array from asset
                $hostnames = array_pull($asset, 'hostNames');

                // build new hostNames array
                $hostnames_array = [];
                foreach ($hostnames as $hostname) {
                    $hostnames_array[$hostname['source']] = $hostname['name'];
                }

                $asset['hostNames'] = $hostnames_array;
            }

            // deal with links array
            if (array_key_exists('links', $asset)) {
                // pull links array from asset
                $links = array_pull($asset, 'links');

                // build new links array
                $links_array = [];
                foreach ($links as $link) {
                    $links_array[$link['rel']] = $link['href'];
                }

                // add links array back to asset
                $asset['links'] = $links_array;
            }

            // deal with users array
            if (array_key_exists('users', $asset)) {
                // pull users array from asset
                $users = array_pull($asset, 'users');

                $local_account_count = 0;

                // build new users array
                $users_array = [];
                foreach ($users as $user) {
                    // negative user id implies local account, many have the same id so we need
                    // to create unique array keys for each
                    if ($user['id'] < 0) {
                        $local_account_count++;

                        if (array_key_exists('fullName', $user)) {
                            // if fullName exists then use that value
                            $users_array['LOCAL-'.$local_account_count] = $user['fullName'];
                        } elseif (array_key_exists('name', $user)) {
                            // otherwise if name exists then use that value
                            $users_array['LOCAL-'.$local_account_count] = $user['name'];
                        } else {
                            // otherwise...
                            $users_array['LOCAL-'.$local_account_count] = '';
                        }
                    } else {
                        if (array_key_exists('fullName', $user)) {
                            // if fullName exists then use that value
                            $users_array[$user['id']] = $user['fullName'];
                        } elseif (array_key_exists('name', $user)) {
                            // otherwise if name exists then use that value
                            $users_array[$user['id']] = $user['name'];
                        } else {
                            // otherwise...
                            $users_array[$user['id']] = '';
                        }
                    }
                }

                // add users array back to asset
                $asset['users'] = $users_array;
            }

            // deal with userGroups array
            if (array_key_exists('userGroups', $asset)) {
                // pull userGroups array from asset
                $usergroups = array_pull($asset, 'userGroups');

                // build new userGroups array
                $usergroups_array = [];
                foreach ($usergroups as $group) {
                    $usergroups_array[$group['id']] = $group['name'];
                }

                // add userGroups array back to asset
                $asset['userGroups'] = $usergroups_array;
            }

            // deal with addresses array
            if (array_key_exists('addresses', $asset)) {
                // pull addresses array from asset
                $addresses = array_pull($asset, 'addresses');

                // build new addresses array
                $addresses_array = [];
                $count = 0;

                foreach ($addresses as $address) {
                    $address_keys = array_keys($address);

                    foreach ($address_keys as $key) {
                        $addresses_array[$key.'-'.$count] = $address[$key];
                    }

                    $count++;
                }

                // add addresses array back to asset
                $asset['addresses'] = $addresses_array;
            }

            $assets[] = $asset;
        }

        Log::info('[+] data normalization complete');

        // dump final array to file
        file_put_contents(storage_path('app/collections/nexpose_assets.json'), \Metaclassing\Utility::encodeJson($assets));

        // instantiate a Kafka producer config and set the broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate new Kafka producer
        $producer = new \Kafka\Producer();

        Log::info('[+] sending ['.count($assets).'] Nexpose assets to Kafka...');

        // cycle through data
        foreach ($assets as $asset) {

            // ship to Kafka
            $result = $producer->send([
                [
                    'topic' => 'nexpose-assets',
                    'value' => \Metaclassing\Utility::encodeJson($asset),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[!] Error sending Nexpose asset to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            }
        }

        Log::info('[+] * Nexpose assets completed! *');
    }
}
