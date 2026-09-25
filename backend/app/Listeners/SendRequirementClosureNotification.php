<?php

namespace App\Listeners;

use App\Events\RequirementFinalizedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRequirementClosureNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RequirementFinalizedEvent $event): void
    {
        //
    }
}
