<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecurityCenterMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_center_mediums', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('dns_name');
            $table->integer('severity_id');
            $table->text('severity_name');
            $table->text('risk_factor');
            $table->dateTime('first_seen');        // requires ms to datetime conversion
            $table->dateTime('last_seen');        // requires ms to datetime conversion
            $table->text('protocol');
            $table->ipAddress('ip_address');
            $table->integer('port');
            $table->macAddress('mac_address');
            $table->text('exploit_available');
            $table->text('exploit_ease')->nullable();
            $table->text('exploit_frameworks')->nullable();
            $table->dateTime('vuln_public_date')->nullable();    // requires ms to datetime and handling -1
            $table->dateTime('patch_public_date')->nullable();    // requires ms to datetime and handling -1
            $table->integer('has_been_mitigated');
            $table->longText('solution');
            $table->integer('plugin_id');
            $table->mediumText('plugin_name');
            $table->text('synopsis');
            $table->text('cpe')->nullable();
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
        Schema::dropIfExists('security_center_mediums');
    }
}
