<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDatesToCylanceThreatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cylance_threats', function (Blueprint $table) {
            $table->dateTime('first_found')->after('suspicious_in_devices');
            $table->dateTime('last_found')->after('first_found');
            $table->dateTime('last_found_active')->nullable()->after('last_found');
            $table->dateTime('last_found_allowed')->nullable()->after('last_found_active');
            $table->dateTime('last_found_blocked')->nullable()->after('last_found_allowed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cylance_threats', function (Blueprint $table) {
            //
        });
    }
}
