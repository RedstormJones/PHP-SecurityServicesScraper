<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateServiceNowIdmTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_now_idm_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('task_id');
            $table->dateTime('created_on');
            $table->text('created_by');
            $table->text('sys_id');
            $table->text('class_name');
            $table->text('parent')->nullable();
            $table->text('active');
            $table->dateTime('updated_on')->nullable();
            $table->text('updated_by')->nullable();
            $table->dateTime('opened_at');
            $table->text('opened_by');
            $table->dateTime('closed_at')->nullable();
            $table->text('closed_by')->nullable();
            $table->text('close_notes');
            $table->text('initial_assignment_group');
            $table->text('assignment_group');
            $table->text('assigned_to');
            $table->text('state');
            $table->text('urgency');
            $table->text('impact');
            $table->text('priority');
            $table->text('time_worked')->nullable();
            $table->text('short_description');
            $table->text('description');
            $table->text('work_notes');
            $table->text('comments');
            $table->integer('reassignment_count');
            $table->text('district');
            $table->text('company');
            $table->text('department');
            $table->integer('modified_count');
            $table->text('location');
            $table->text('cause_code');
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
        Schema::dropIfExists('service_now_idm_tasks');
    }
}
