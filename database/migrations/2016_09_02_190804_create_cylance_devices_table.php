<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCylanceDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cylance_devices', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('device_id');		// MAKE THIS UNIQUE
            $table->text('device_name');
            $table->text('zones_text');
            $table->integer('files_unsafe');
            $table->integer('files_quarantined');
            $table->integer('files_abnormal');
            $table->integer('files_waived');
            $table->integer('files_analyzed');
            $table->text('agent_version_text');
            $table->text('last_users_text');
            $table->text('os_versions_text')->nullable();
            $table->text('ip_addresses_text');
            $table->text('mac_addresses_text');
            $table->text('policy_name');
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
        Schema::dropIfExists('cylance_devices');
    }
}
