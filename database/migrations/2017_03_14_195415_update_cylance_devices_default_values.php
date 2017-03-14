<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCylanceDevicesDefaultValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cylance_devices', function (Blueprint $table) {
            DB::statement('ALTER TABLE cylance_devices MODIFY files_unsafe INT DEFAULT 0');
            DB::statement('ALTER TABLE cylance_devices MODIFY files_quarantined INT DEFAULT 0');
            DB::statement('ALTER TABLE cylance_devices MODIFY files_abnormal INT DEFAULT 0');
            DB::statement('ALTER TABLE cylance_devices MODIFY files_waived INT DEFAULT 0');
            DB::statement('ALTER TABLE cylance_devices MODIFY files_analyzed INT DEFAULT 0');
            DB::statement('ALTER TABLE cylance_devices MODIFY agent_version_text TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE cylance_devices MODIFY last_users_text TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE cylance_devices MODIFY ip_addresses_text TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE cylance_devices MODIFY mac_addresses_text TEXT DEFAULT NULL');
            DB::statement('ALTER TABLE cylance_devices MODIFY policy_name TEXT DEFAULT NULL');
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
