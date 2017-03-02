<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeCylanceScoreNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('ALTER TABLE cylance_threats MODIFY cylance_score INT DEFAULT NULL');

        /*
        Schema::table('cylance_threats', function (Blueprint $table) {
            $table->integer('cylance_score')->nullable()->change();
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
        Schema::table('cylance_threats', function (Blueprint $table) {
            //
        });
    }
}
