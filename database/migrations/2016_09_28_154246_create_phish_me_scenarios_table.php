<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePhishMeScenariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phish_me_scenarios', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
			$table->softDeletes();
			$table->text('reportable_id');
			$table->string('reportable_type');
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
        Schema::dropIfExists('phish_me_scenarios');
    }
}
