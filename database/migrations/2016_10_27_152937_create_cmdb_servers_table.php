<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCmdbServersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cmdb_servers', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('sys_id');
            $table->text('name');
            $table->dateTime('created_on');
            $table->dateTime('updated_on');
            $table->text('created_by');
            $table->text('updated_by');
            $table->text('classification');
            $table->integer('modified_count');
            $table->text('short_description');
            $table->text('os_domain');
            $table->text('ip_address');
            $table->text('remote_mgmt_ip')->nullable();
            $table->text('application')->nullable();
            $table->text('environment')->nullable();
            $table->text('data_center')->nullable();
            $table->text('site_id')->nullable();
            $table->text('business_process')->nullable();
            $table->text('business_function')->nullable();
            $table->text('notes')->nullable();
            $table->text('product')->nullable();
            $table->text('product_group')->nullable();
            $table->text('antivirus_exclusions')->nullable();
            $table->text('ktg_contact')->nullable();
            $table->text('virtual');
            $table->text('used_for')->nullable();
            $table->text('firewall_status')->nullable();
            $table->text('os')->nullable();
            $table->text('os_service_pack')->nullable();
            $table->text('os_version')->nullable();
            $table->text('disk_space')->nullable();
            $table->text('operational_status')->nullable();
            $table->text('model_number')->nullable();
            $table->text('serial_number')->nullable();
            $table->text('managed_by')->nullable();
            $table->text('owned_by')->nullable();
            $table->text('supported_by')->nullable();
            $table->text('support_group')->nullable();
            $table->text('location')->nullable();
            $table->text('bpo')->nullable();
            $table->text('assigned_to')->nullable();
            $table->text('district')->nullable();
            $table->json('data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cmdb_servers');
    }
}
