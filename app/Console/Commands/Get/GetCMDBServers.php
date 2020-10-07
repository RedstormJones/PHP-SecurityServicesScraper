<?php

namespace App\Console\Commands\Get;

require_once app_path('Console/Crawler/Crawler.php');

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetCMDBServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:cmdbservers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new CMDB servers';

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
        /****************************
         * [1] Get all CMDB servers *
         ****************************/

        Log::info('[GetCMDBServers.php] Starting ServiceNow API Poll!');

        // setup cookiejar
        $cookiejar = storage_path('app/cookies/snow_cookie.txt');

        // instantiate crawler
        $crawler = new \Crawler\Crawler($cookiejar);

        // point url to CMDB server list
        $url = 'https:/'.'/kiewit.service-now.com/api/now/v1/table/cmdb_ci_server?sysparm_display_value=true';

        // setup HTTP headers and add them to crawler
        $headers = [
            'accept: application/json',
            'authorization: Basic '.getenv('SERVICENOW_AUTH'),
            'cache-control: no-cache',
        ];
        curl_setopt($crawler->curl, CURLOPT_HTTPHEADER, $headers);

        // send request and capture response
        $json_response = $crawler->get($url);

        // dump response to file
        file_put_contents(storage_path('app/responses/cmdb.response'), $json_response);

        // JSON decode response
        $response = \Metaclassing\Utility::decodeJson($json_response);

        // get the data we care about and log how many records we got
        $servers = $response['result'];
        Log::info('[GetCMDBServers.php] total server count: '.count($servers));

        $cmdb_servers = [];

        foreach ($servers as $server) {
            // handle empty strings and null values
            $managed_by = $this->handleNull($server['managed_by']);
            $owned_by = $this->handleNull($server['owned_by']);
            $supported_by = $this->handleNull($server['supported_by']);
            $support_group = $this->handleNull($server['support_group']);
            $location = $this->handleNull($server['location']);
            $bpo = $this->handleNull($server['u_bpo']);
            $assigned_to = $this->handleNull($server['assigned_to']);
            $assignment_group = $this->handleNull($server['assignment_group']);
            $district = $this->handleNull($server['u_district']);
            $manufacturer = $this->handleNull($server['manufacturer']);
            $cpu_manufacturer = $this->handleNull($server['cpu_manufacturer']);
            $build_by = $this->handleNull($server['u_build_by']);
            $asset = $this->handleNull($server['asset']);
            $model_id = $this->handleNull($server['model_id']);
            $company = $this->handleNull($server['company']);
            $department = $this->handleNull($server['department']);
            $sys_domain = $this->handleNull($server['sys_domain']);

            // format created on date
            $created_on_pieces = explode(' ', $server['sys_created_on']);
            $created_on = $created_on_pieces[0].'T'.$created_on_pieces[1];

            // if updated on date exists then format otherwise set to null
            if ($server['sys_updated_on']) {
                $updated_on_pieces = explode(' ', $server['sys_updated_on']);
                $updated_on = $updated_on_pieces[0].'T'.$updated_on_pieces[1];
            } else {
                $updated_on = null;
            }

            // if order date exists then format otherwise set to null
            if ($server['order_date']) {
                $order_date_pieces = explode(' ', $server['order_date']);
                $order_date = $order_date_pieces[0].'T'.$order_date_pieces[1];
            } else {
                $order_date = null;
            }

            // if first discovered date exists then format otherwise set to null
            if ($server['first_discovered']) {
                $first_disc_pieces = explode(' ', $server['first_discovered']);
                $first_discovered = $first_disc_pieces[0].'T'.$first_disc_pieces[1];
            } else {
                $first_discovered = null;
            }

            // if last discovered date exists then format otherwise set to null
            if ($server['last_discovered']) {
                $last_disc_pieces = explode(' ', $server['last_discovered']);
                $last_discovered = $last_disc_pieces[0].'T'.$last_disc_pieces[1];
            } else {
                $last_discovered = null;
            }

            // if checked in date exists then format otherwise set to null
            if ($server['checked_in']) {
                $checked_in_pieces = explode(' ', $server['checked_in']);
                $checked_in = $checked_in_pieces[0].'T'.$checked_in_pieces[1];
            } else {
                $checked_in = null;
            }

            // if checked out date exists then format otherwise set to null
            if ($server['checked_out']) {
                $checked_out_pieces = explode(' ', $server['checked_out']);
                $checked_out = $checked_out_pieces[0].'T'.$checked_out_pieces[1];
            } else {
                $checked_out = null;
            }

            // add server to final array
            $cmdb_servers[] = [
                'x_bmgr_support_ent_bomgar_appliance_name'  => $server['x_bmgr_support_ent_bomgar_appliance_name'],
                'can_print'                                 => $server['can_print'],
                'subcategory'                               => $server['subcategory'],
                'install_status'                            => $server['install_status'],
                'hardware_status'                           => $server['hardware_status'],
                'assignment_group'                          => $assignment_group['display_value'],
                'u_siteid'                                  => $server['u_siteid'],
                'monitor'                                   => $server['monitor'],
                'ip_address'                                => $server['ip_address'],
                'company'                                   => $company['display_value'],
                'u_antivirus_exclusions'                    => $server['u_antivirus_exclusions'],
                'u_outage_message'                          => $server['u_outage_message'],
                'gl_account'                                => $server['gl_account'],
                'u_application'                             => $server['u_application'],
                'u_build_date'                              => $server['u_build_date'],
                'delivery_date'                             => $server['delivery_date'],
                'u_environment'                             => $server['u_environment'],
                'u_backup_location'                         => $server['u_backup_location'],
                'x_bmgr_support_ent_bomgar_jumpoint'        => $server['x_bmgr_support_ent_bomgar_jumpoint'],
                'vendor'                                    => $server['vendor'],
                'managed_by'                                => $managed_by['display_value'],
                'cpu_core_count'                            => $server['cpu_core_count'],
                'os_version'                                => $server['os_version'],
                'u_sccm_last_logged_in'                     => $server['u_sccm_last_logged_in'],
                'model_number'                              => $server['model_number'],
                'mac_address'                               => $server['mac_address'],
                'ram'                                       => $server['ram'],
                'sys_id'                                    => $server['sys_id'],
                'disk_space'                                => $server['disk_space'],
                'u_dhcp_server'                             => $server['u_dhcp_server'],
                'os_domain'                                 => $server['os_domain'],
                'u_product'                                 => $server['u_product'],
                'asset_tag'                                 => $server['asset_tag'],
                'invoice_number'                            => $server['invoice_number'],
                'supported_by'                              => $supported_by['display_value'],
                'manufacturer'                              => $manufacturer['display_value'],
                'department'                                => $department['display_value'],
                'cost'                                      => $server['cost'],
                'order_date'                                => $order_date,
                'cd_rom'                                    => $server['cd_rom'],
                'justification'                             => $server['justification'],
                'install_date'                              => $server['install_date'],
                'u_dr_tier'                                 => $server['u_dr_tier'],
                'u_functional_lead'                         => $server['u_functional_lead'],
                'short_description'                         => $server['short_description'],
                'u_bpo_approver'                            => $server['u_bpo_approver'],
                'fqdn'                                      => $server['fqdn'],
                'model_id'                                  => $model_id['display_value'],
                'owned_by'                                  => $owned_by['display_value'],
                'firewall_status'                           => $server['firewall_status'],
                'sys_domain'                                => $sys_domain['display_value'],
                'cpu_count'                                 => $server['cpu_count'],
                'os_service_pack'                           => $server['os_service_pack'],
                'u_rack_unit'                               => $server['u_rack_unit'],
                'sys_created_by'                            => $server['sys_created_by'],
                'sys_tags'                                  => $server['sys_tags'],
                'location'                                  => $location['display_value'],
                'used_for'                                  => $server['used_for'],
                'due'                                       => $server['due'],
                'schedule'                                  => $server['schedule'],
                'serial_number'                             => $server['serial_number'],
                'u_owned_by_back_up'                        => $server['u_owned_by_back_up'],
                'u_sccm_top_console_user'                   => $server['u_sccm_top_console_user'],
                'assigned_to'                               => $assigned_to['display_value'],
                'u_rack_number'                             => $server['u_rack_number'],
                'u_function'                                => $server['u_function'],
                'dns_domain'                                => $server['dns_domain'],
                'cpu_type'                                  => $server['cpu_type'],
                'host_name'                                 => $server['host_name'],
                'u_dhcp_scope'                              => $server['u_dhcp_scope'],
                'assigned'                                  => $server['assigned'],
                'support_group'                             => $support_group['display_value'],
                'start_date'                                => $server['start_date'],
                'warranty_expiration'                       => $server['warranty_expiration'],
                'due_in'                                    => $server['due_in'],
                'chassis_type'                              => $server['chassis_type'],
                'u_auto_route'                              => $server['u_auto_route'],
                'first_discovered'                          => $first_discovered,
                'cost_cc'                                   => $server['cost_cc'],
                'u_notes'                                   => $server['u_notes'],
                'sys_class_name'                            => $server['sys_class_name'],
                'u_build_by'                                => $build_by['display_value'],
                'default_gateway'                           => $server['default_gateway'],
                'checked_out'                               => $checked_out,
                'os_address_width'                          => $server['os_address_width'],
                'category'                                  => $server['category'],
                'cd_speed'                                  => $server['cd_speed'],
                'maintenance_schedule'                      => $server['maintenance_schedule'],
                'sys_updated_by'                            => $server['sys_updated_by'],
                'floppy'                                    => $server['floppy'],
                'sys_created_on'                            => $created_on,
                'sys_updated_on'                            => $updated_on,
                'u_data_center'                             => $server['u_data_center'],
                'discovery_source'                          => $server['discovery_source'],
                'unverified'                                => $server['unverified'],
                'last_discovered'                           => $last_discovered,
                'lease_id'                                  => $server['lease_id'],
                'attributes'                                => $server['attributes'],
                'skip_sync'                                 => $server['skip_sync'],
                'hardware_substatus'                        => $server['hardware_substatus'],
                'comments'                                  => $server['comments'],
                'sys_mod_count'                             => $server['sys_mod_count'],
                'u_remote_mgmt_ip'                          => $server['u_remote_mgmt_ip'],
                'virtual'                                   => $server['virtual'],
                'asset'                                     => $asset['display_value'],
                'cpu_speed'                                 => $server['cpu_speed'],
                'u_product_group'                           => $server['u_product_group'],
                'cpu_name'                                  => $server['cpu_name'],
                'u_district'                                => $district['display_value'],
                'name'                                      => $server['name'],
                'checked_in'                                => $checked_in,
                'os'                                        => $server['os'],
                'cpu_manufacturer'                          => $cpu_manufacturer['display_value'],
                'u_bpo'                                     => $bpo['display_value'],
                'fault_count'                               => $server['fault_count'],
                'u_ktg_contact'                             => $server['u_ktg_contact'],
                'po_number'                                 => $server['po_number'],
                'operational_status'                        => $server['operational_status'],
                'change_control'                            => $server['change_control'],
                'u_business_process'                        => $server['u_business_process'],
                'u_dr_rating'                               => $server['u_dr_rating'],
                'purchase_date'                             => $server['purchase_date'],
                'classification'                            => $server['classification'],
                'correlation_id'                            => $server['correlation_id'],
                'dr_backup'                                 => $server['dr_backup'],
                'cpu_core_thread'                           => $server['cpu_core_thread'],
                'sys_domain_path'                           => $server['sys_domain_path'],
                'u_business_lines_list'                     => $server['u_business_lines_list'],
                'form_factor'                               => $server['form_factor'],
            ];
        }

        // JSON encode and dump CMDB servers to file
        file_put_contents(storage_path('app/collections/cmdb_servers_collection.json'), \Metaclassing\Utility::encodeJson($cmdb_servers));

        // instantiate Kafka producer config and set broker IP
        $config = \Kafka\ProducerConfig::getInstance();
        $config->setMetadataBrokerList(getenv('KAFKA_BROKERS'));

        // instantiate Kafka producer
        $producer = new \Kafka\Producer();

        // cycle through servers
        foreach ($cmdb_servers as $server) {
            // add upsert datetime
            $server['upsert_date'] = Carbon::now()->toAtomString();

            // ship data to Kafka
            $result = $producer->send([
                [
                    'topic' => 'servicenow_cmdb_servers',
                    'value' => \Metaclassing\Utility::encodeJson($server),
                ],
            ]);

            // check for and log errors
            if ($result[0]['data'][0]['partitions'][0]['errorCode']) {
                Log::error('[GetCMDBServers.php] Error sending to Kafka: '.$result[0]['data'][0]['partitions'][0]['errorCode']);
            } else {
                Log::info('[GetCMDBServers.php] Data successfully sent to Kafka: '.$server['name']);
            }
        }

        Log::info('[GetCMDBServers.php] CMDB servers completed! *'.PHP_EOL);
    }

    /**
     * Function to handle null values.
     *
     * @return array
     */
    public function handleNull($data)
    {
        // if data is not null then check if 'display_value' is set
        if ($data) {
            // if 'display_value' is set then just return data
            if (isset($data['display_value'])) {
                return $data;
            } else {
                /*
                 otherwise, create an one element array with a key of 'display_value'
                 and a value of whatever the data is, and return it
                */
                $some_data['display_value'] = $data;

                return $some_data;
            }
        } else {
            /*
             otherwise, create an one element array with a key of 'display_value'
             and a value of null, and return it
            */
            $some_data['display_value'] = null;

            return $some_data;
        }
    }
}
