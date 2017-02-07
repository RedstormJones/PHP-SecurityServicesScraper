<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSecurityCenterSumIpVulnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_center_sum_ip_vulns', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->ipAddress('ip_address');
            $table->text('dns_name');
            $table->integer('score');
            $table->integer('total');
            $table->integer('severity_info');
            $table->integer('severity_low');
            $table->integer('severity_medium');
            $table->integer('severity_high');
            $table->integer('severity_critical');
            $table->macAddress('mac_address');
            $table->text('policy_name');
            $table->text('plugin_set');
            $table->text('netbios_name');
            $table->text('os_cpe');
            $table->text('bios_guid');
            $table->integer('repository_id');
            $table->text('repository_name');
            $table->text('repository_desc');
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
        Schema::dropIfExists('security_center_sum_ip_vulns');
    }
}
