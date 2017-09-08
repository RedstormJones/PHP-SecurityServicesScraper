<?php

namespace App\Jobs;

use App\Cylance\CylanceDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendCylanceDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $cylance_device = [];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($cylance_device)
    {
        $this->cylance_device = $cylance_device;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        
    }
}
