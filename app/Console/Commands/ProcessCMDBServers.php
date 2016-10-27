<?php

namespace App\Console\Commands;

use App\ServiceNow\cmdbServer;
use Illuminate\Console\Command;

class ProcessCMDBServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:cmdbservers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get new CMDB server records and update the database';

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
        $contents = file_get_contents(storage_path('app/collections/cmdb_servers_collection.json'));

        $cmdb_servers = \Metaclassing\Utility::decodeJson($contents);

        foreach ($cmdb_servers as $server) {
            $exists = cmdbServer::where('sys_id', $server['sys_id'])->value('id');

            if ($exists) {
                $managed_by = $this->handleNull($server['managed_by']);
                $owned_by = $this->handleNull($server['owned_by']);
                $supported_by = $this->handleNull($server['supported_by']);
                $support_group = $this->handleNull($server['support_group']);
                $location = $this->handleNull($server['location']);
                $bpo = $this->handleNull($server['u_bpo']);
                $assigned_to = $this->handleNull($server['assigned_to']);
                $district = $this->handleNull($server['u_district']);

                $servermodel = cmdbServer::find($exists);

                $servermodel->update([
                    'updated_on'            => $server['sys_updated_on'],
                    'updated_by'            => $server['sys_updated_by'],
                    'modified_count'        => $server['sys_mod_count'],
                    'short_description'     => $server['short_description'],
                    'ip_address'            => $server['ip_address'],
                    'remote_mgmt_ip'        => $server['u_remote_mgmt_ip'],
                    'application'           => $server['u_application'],
                    'environment'           => $server['u_environment'],
                    'data_center'           => $server['u_data_center'],
                    'site_id'               => $server['u_siteid'],
                    'business_process'      => $server['u_business_process'],
                    'function'              => $server['u_function'],
                    'notes'                 => $server['u_notes'],
                    'product'               => $server['u_product'],
                    'product_group'         => $server['u_product_group'],
                    'antivirus_exclusions'  => $server['u_antivirus_exclusions'],
                    'ktg_contact'           => $server['u_ktg_contact'],
                    'virtual'               => $server['virtual'],
                    'used_for'              => $server['used_for'],
                    'firewall_status'       => $server['firewall_status'],
                    'os'                    => $server['os'],
                    'os_service_pack'       => $server['os_service_pack'],
                    'os_version'            => $server['os_version'],
                    'disk_space'            => $server['disk_space'],
                    'operational_status'    => $server['operational_status'],
                    'bpo'                   => $bpo['display_value'],
                    'assigned_to'           => $assigned_to['display_value'],
                    'district'              => $district['display_value'],
                    'managed_by'            => $managed_by['display_value'],
                    'owned_by'              => $owned_by['display_value'],
                    'supported_by'          => $supported_by['display_value'],
                    'support_group'         => $support_group['display_value'],
                    'location'              => $location['display_value'],
                    'data'                  => \Metaclassing\Utility::encodeJson($server),
                ]);

                // touch server model to update 'updated_at' timestamp in case nothing was changed
                $servermodel->touch();

                echo 'server already exists: '.$server['name'].PHP_EOL;
            } else {
                echo 'creating new server record: '.$server['name'].PHP_EOL;

                $managed_by = $this->handleNull($server['managed_by']);
                $owned_by = $this->handleNull($server['owned_by']);
                $supported_by = $this->handleNull($server['supported_by']);
                $support_group = $this->handleNull($server['support_group']);
                $location = $this->handleNull($server['location']);
                $bpo = $this->handleNull($server['u_bpo']);
                $assigned_to = $this->handleNull($server['assigned_to']);
                $district = $this->handleNull($server['u_district']);

                $new_server = new cmdbServer();

                $new_server->sys_id = $server['sys_id'];
                $new_server->name = $server['name'];
                $new_server->created_on = $server['sys_created_on'];
                $new_server->updated_on = $server['sys_updated_on'];
                $new_server->created_by = $server['sys_created_by'];
                $new_server->updated_by = $server['sys_updated_by'];
                $new_server->classification = $server['classification'];
                $new_server->modified_count = $server['sys_mod_count'];
                $new_server->short_description = $server['short_description'];
                $new_server->os_domain = $server['os_domain'];
                $new_server->ip_address = $server['ip_address'];
                $new_server->remote_mgmt_ip = $server['u_remote_mgmt_ip'];
                $new_server->application = $server['u_application'];
                $new_server->environment = $server['u_environment'];
                $new_server->data_center = $server['u_data_center'];
                $new_server->site_id = $server['u_siteid'];
                $new_server->business_process = $server['u_business_process'];
                $new_server->business_function = $server['u_function'];
                $new_server->notes = $server['u_notes'];
                $new_server->product = $server['u_product'];
                $new_server->product_group = $server['u_product_group'];
                $new_server->antivirus_exclusions = $server['u_antivirus_exclusions'];
                $new_server->ktg_contact = $server['u_ktg_contact'];
                $new_server->virtual = $server['virtual'];
                $new_server->used_for = $server['used_for'];
                $new_server->firewall_status = $server['firewall_status'];
                $new_server->os = $server['os'];
                $new_server->os_service_pack = $server['os_service_pack'];
                $new_server->os_version = $server['os_version'];
                $new_server->disk_space = $server['disk_space'];
                $new_server->operational_status = $server['operational_status'];
                $new_server->model_number = $server['model_number'];
                $new_server->serial_number = $server['serial_number'];
                $new_server->managed_by = $managed_by['display_value'];
                $new_server->owned_by = $owned_by['display_value'];
                $new_server->supported_by = $supported_by['display_value'];
                $new_server->support_group = $support_group['display_value'];
                $new_server->location = $location['display_value'];
                $new_server->bpo = $bpo['display_value'];
                $new_server->assigned_to = $assigned_to['display_value'];
                $new_server->district = $district['display_value'];
                $new_server->data = \Metaclassing\Utility::encodeJson($server);

                $new_server->save();
            }
        }

        $this->processDeletes();
    }   // end of function handle()

    /**
     * Function to handle null values.
     *
     * @return array
     */
    public function handleNull($data)
    {
        // if data is not null then just return it
        if ($data) {
            return $data;
        } else {
            /*
            * otherwise, create and set the key 'display_vaue'
            * to the literal string 'null' and return it
            */
            $data['display_value'] = 'null';

            return $data;
        }
    }

    /**
     * Delete old CylanceDevice models.
     *
     * @return void
     */
    public function processDeletes()
    {
        $today = new \DateTime('now');
        $yesterday = $today->modify('-1 day');
        $delete_date = $yesterday->format('Y-m-d H:i:s');

        $servers = cmdbServer::where('updated_at', '<', $delete_date)->get();

        foreach($servers as $server)
        {
            echo 'deleting server: '.$server->name.PHP_EOL;
            $server->delete();
        }
    }
}
