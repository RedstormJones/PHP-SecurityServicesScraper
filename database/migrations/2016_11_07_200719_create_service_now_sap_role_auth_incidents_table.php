<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceNowSapRoleAuthIncidentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_now_sap_role_auth_incidents', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('incident_id');
            $table->dateTime('opened_at');
            $table->text('closed_at')->nullable();
            $table->text('state');
            $table->text('duration');
            $table->text('initial_assignment_group');
            $table->text('sys_id');
            $table->text('time_worked')->nullable();
            $table->integer('reopen_count');
            $table->text('urgency');
            $table->text('impact');
            $table->text('severity');
            $table->text('priority');
            $table->text('email_contact');
            $table->text('description');
            $table->text('district');
            $table->dateTime('updated_on')->nullable();
            $table->text('active');
            $table->text('assignment_group');
            $table->text('caller_id');
            $table->text('department');
            $table->integer('reassignment_count');
            $table->text('short_description');
            $table->text('resolved_by');
            $table->text('calendar_duration');
            $table->text('assigned_to');
            $table->text('resolved_at')->nullable();
            $table->text('cmdb_ci');
            $table->text('opened_by');
            $table->text('escalation');
            $table->integer('modified_count');
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
        Schema::dropIfExists('service_now_sap_role_auth_incidents');
    }
}
