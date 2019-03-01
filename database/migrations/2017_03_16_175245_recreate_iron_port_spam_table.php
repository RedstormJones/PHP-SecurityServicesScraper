<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreateIronPortSpamTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('iron_port_spam_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->integer('mid');
            $table->text('subject');
            $table->text('size');
            $table->text('quarantine_names');
            $table->dateTime('time_added');
            $table->text('reason');
            $table->text('recipients');
            $table->text('sender');
            $table->text('esa_id');
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
        Schema::dropIfExists('iron_port_spam_emails');

        /*
        Schema::table('iron_port_spam_emails', function (Blueprint $table) {

        });
        */
    }
}
