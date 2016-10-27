<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->text('cmdb_id');
            $table->text('name');
            $table->dateTime('created_on');
            $table->dateTime('updated_on');
            $table->text('created_by');
            $table->text('updated_by');
            $table->text('class_name');
            $table->integer('modified_count');
            $table->text('serial_number');
            $table->text('managed_by')->nullable();
            $table->text('owned_by')->nullable();
            $table->text('supported_by')->nullable();
            $table->text('support_group')->nullable();
            $table->text('location')->nullable();
            $table->text('department')->nullable();
            $table->text('company')->nullable();
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
