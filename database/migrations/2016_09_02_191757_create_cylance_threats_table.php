<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCylanceThreatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cylance_threats', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('threat_id');        // MAKE THIS UNIQUE
            $table->text('common_name');
            $table->integer('cylance_score');
            $table->integer('active_in_devices');
            $table->integer('allowed_in_devices');
            $table->integer('blocked_in_devices');
            $table->integer('suspicious_in_devices');
            $table->text('md5')->nullable();
            $table->integer('virustotal')->nullable();
            $table->text('full_classification');
            $table->text('is_unique_to_cylance');
            $table->text('detected_by');
            $table->integer('threat_priority');
            $table->text('current_model');
            $table->integer('priority');
            $table->bigInteger('file_size')->nullable();
            $table->text('global_quarantined');
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
        Schema::dropIfExists('cylance_threats');
    }
}
