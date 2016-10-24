<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInsideHostTrafficSnapshotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inside_host_traffic_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->integer('application_id');
            $table->text('application_name');
            $table->dateTime('time_period');
            $table->bigInteger('traffic_outbound_Bps');
            $table->bigInteger('traffic_inbound_Bps');
            $table->bigInteger('traffic_within_Bps');
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
        Schema::dropIfExists('inside_host_traffic_snapshots');
    }
}
