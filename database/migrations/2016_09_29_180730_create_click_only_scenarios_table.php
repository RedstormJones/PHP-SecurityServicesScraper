<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClickOnlyScenariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('click_only_scenarios', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
			$table->softDeletes();
            $table->text('scenario_id');
            $table->text('scenario_type');
            $table->text('email');
            $table->text('recipient_name');
            $table->text('recipient_group');
            $table->text('department');
            $table->text('location')->nullable();
            $table->text('clicked_link');
            $table->timestamp('clicked_link_timestamp')->nullable();
            $table->text('reported_phish');
            $table->text('new_repeat_reporter');
            $table->timestamp('reported_phish_timestamp')->nullable();
            $table->integer('time_to_report')->nullable();
            $table->ipAddress('remote_ip')->nullable();
            $table->text('geoip_country');
            $table->text('geoip_city');
            $table->text('geoip_organization');
            $table->text('last_dsn');
            $table->text('last_email_status');
            $table->timestamp('last_email_status_timestamp')->nullable();
            $table->text('language')->nullable();
            $table->text('browser')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('mobile')->nullable();
			$table->integer('seconds_spent_on_education')->nullable();
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
        Schema::dropIfExists('click_only_scenarios');
    }
}
