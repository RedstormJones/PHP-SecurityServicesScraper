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
            $exists = cmdbServer::where('cmdb_id', $server['sys_id'])->value('id');

            if ($exists) {
                $managed_by = $this->handleNull($server['managed_by']);
                $owned_by = $this->handleNull($server['owned_by']);
                $supported_by = $this->handleNull($server['supported_by']);
                $support_group = $this->handleNull($server['support_group']);
                $location = $this->handleNull($server['location']);
                $department = $this->handleNull($server['department']);
                $company = $this->handleNull($server['company']);

                $servermodel = cmdbServer::find($exists);

                $servermodel->update([
                    'updated_on'        => $server['sys_updated_on'],
                    'modified_count'    => $server['sys_mod_count'],
                    'managed_by'        => $managed_by['display_value'],
                    'owned_by'          => $owned_by['display_value'],
                    'supported_by'      => $supported_by['display_value'],
                    'support_group'     => $support_group['display_value'],
                    'location'          => $location['display_value'],
                    'department'        => $department['display_value'],
                    'company'           => $company['display_value'],
                    'data'              => \Metaclassing\Utility::encodeJson($server),
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
                $department = $this->handleNull($server['department']);
                $company = $this->handleNull($server['company']);

                $new_server = new cmdbServer();

                $new_server->cmdb_id = $server['sys_id'];
                $new_server->name = $server['name'];
                $new_server->created_on = $server['sys_created_on'];
                $new_server->updated_on = $server['sys_updated_on'];
                $new_server->created_by = $server['sys_created_by'];
                $new_server->updated_by = $server['sys_updated_by'];
                $new_server->class_name = $server['sys_class_name'];
                $new_server->modified_count = $server['sys_mod_count'];
                $new_server->serial_number = $server['serial_number'];
                $new_server->managed_by = $managed_by['display_value'];
                $new_server->owned_by = $owned_by['display_value'];
                $new_server->supported_by = $supported_by['display_value'];
                $new_server->support_group = $support_group['display_value'];
                $new_server->location = $location['display_value'];
                $new_server->department = $department['display_value'];
                $new_server->company = $company['display_value'];
                $new_server->data = \Metaclassing\Utility::encodeJson($server);

                $new_server->save();
            }
        }
    }

   // end of function handle()

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
}
