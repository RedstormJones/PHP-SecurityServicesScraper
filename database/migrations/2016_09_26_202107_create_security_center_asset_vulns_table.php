<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecurityCenterAssetVulnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_center_asset_vulns', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->text('asset_name');
            $table->unsignedInteger('asset_id');
            $table->unsignedInteger('asset_score');
            $table->unsignedInteger('critical_vulns');
            $table->unsignedInteger('high_vulns');
            $table->unsignedInteger('medium_vulns');
            $table->unsignedInteger('total_vulns');
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
        Schema::dropIfExists('security_center_asset_vulns');
    }
}
