<?php

namespace App\Listeners;

use App\Events\SCCMSystemsProcessingInitiated;
use Illuminate\Support\Facades\Artisan;

class StartSCCMSystemsProcessing
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param SCCMSystemsProcessingInitiated $event
     *
     * @return void
     */
    public function handle(SCCMSystemsProcessingInitiated $event)
    {
        Artisan::call('get:sccmsystems');
    }
}
