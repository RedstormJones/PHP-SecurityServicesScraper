<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSccmSystemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sccm_systems', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('system_name');
            $table->text('district');
            $table->text('region');
            $table->text('group');
            $table->text('owner');
            $table->integer('days_since_last_logon');
            $table->text('stale_45days')->nullable();
            $table->text('client_status');
            $table->text('client_version');
            $table->text('operating_system');
            $table->text('operating_system_version');
            $table->text('os_roundup');
            $table->text('os_arch');
            $table->text('system_role');
            $table->text('serial_number')->nullable();
            $table->text('chassis_type');
            $table->text('manufacturer');
            $table->text('model');
            $table->text('processor');
            $table->text('image_source');
            $table->dateTime('image_date')->nullable();
            $table->text('coe_compliant');
            $table->text('ps_version');
            $table->integer('patch_total');
            $table->integer('patch_installed');
            $table->integer('patch_missing');
            $table->integer('patch_unknown');
            $table->text('patch_percent');
            $table->text('scep_installed');
            $table->text('cylance_installed');
            $table->text('anyconnect_installed');
            $table->text('anyconnect_websecurity');
            $table->text('bitlocker_status');
            $table->integer('tpm_enabled');
            $table->integer('tpm_activated');
            $table->integer('tpm_owned');
            $table->text('ie_version');
            $table->text('ad_location');
            $table->text('primary_users');
            $table->text('last_logon_username')->nullable();
            $table->dateTime('ad_last_logon')->nullable();
            $table->dateTime('ad_password_last_set')->nullable();
            $table->dateTime('ad_modified')->nullable();
            $table->dateTime('sccm_last_heartbeat')->nullable();
            $table->text('sccm_management_point');
            $table->dateTime('sccm_last_health_eval')->nullable();
            $table->text('sccm_last_health_result');
            $table->date('report_date');
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
        Schema::dropIfExists('sccm_systems');
    }
}
