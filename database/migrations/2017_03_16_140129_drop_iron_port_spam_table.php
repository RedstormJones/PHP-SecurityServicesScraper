<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropIronPortSpamTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('iron_port_spam_emails');
        
        /*
        Schema::table('iron_port_spam_emails', function (Blueprint $table) {
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
        Schema::table('iron_port_spam_emails', function (Blueprint $table) {
            //
        });
    }
}
