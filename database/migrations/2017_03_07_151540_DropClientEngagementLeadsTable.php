<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropClientEngagementLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('district_client_engagement_leads');

        /*
        Schema::table('district_client_engagement_leads', function (Blueprint $table) {
            //
        });
        */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('district_client_engagement_leads', function (Blueprint $table) {
            //
        });
    }
}
