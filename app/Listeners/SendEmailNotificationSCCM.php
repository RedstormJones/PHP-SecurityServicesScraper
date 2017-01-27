<?php

namespace App\Listeners;

use App\Events\SCCMSystemsCompleted;

class SendEmailNotificationSCCM
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
     * @param SCCMSystemsCompleted $event
     *
     * @return void
     */
    public function handle(SCCMSystemsCompleted $event)
    {
        $to = 'ITSecurity@kiewit.com';
        $subject = 'SCCM Systems Processing Completed';
        $message = 'SCCM systems upload and processing have finished.';
        mail($to, $subject, $message);
    }
}
