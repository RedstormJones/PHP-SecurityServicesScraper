<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeCylanceDevicesZonesTextNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cylance_devices', function (Blueprint $table) {
            DB::statement('ALTER TABLE cylance_devices MODIFY zones_text TEXT DEFAULT NULL');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cylance_devices', function (Blueprint $table) {
            //
        });
    }
}
