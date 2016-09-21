<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIncomingEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('incoming_emails', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();
            $table->dateTime('begin_date');
            $table->dateTime('end_date');
            $table->text('sender_domain');
            $table->integer('connections_rejected');
            $table->integer('connections_accepted');
            $table->integer('total_attempted');
            $table->integer('stopped_by_recipient_throttling');
            $table->integer('stopped_by_reputation_filtering');
            $table->integer('stopped_by_content_filter');
            $table->integer('stopped_as_invalid_recipients');
            $table->integer('spam_detected');
            $table->integer('virus_detected');
            $table->integer('amp_detected');
            $table->integer('total_threats');
            $table->integer('marketing');
            $table->integer('social');
            $table->integer('bulk');
            $table->integer('total_graymails');
            $table->integer('clean');
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
        Schema::dropIfExists('incoming_emails');
    }
}
