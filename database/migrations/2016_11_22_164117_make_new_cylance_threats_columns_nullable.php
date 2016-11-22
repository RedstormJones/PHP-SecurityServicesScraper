<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeNewCylanceThreatsColumnsNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE cylance_threats MODIFY signed BOOL DEFAULT NULL");

        /*
        Schema::table('cylance_threats', function (Blueprint $table) {
            $table->boolean('signed')->nullable()->change();
        });
        /**/
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

