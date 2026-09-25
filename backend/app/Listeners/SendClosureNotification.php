<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\RequirementFinalizedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Mail;
// use App\Mail\RequirementClosedMail;

class SendClosureNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * El nombre de la conexión de cola a utilizar (opcional, por defecto toma la de .env).
     */
    public string $connection = 'redis';

    /**
     * El nombre de la cola a la que se enviará el trabajo.
     */
    public string $queue = 'emails';

    /**
     * Maneja el evento.
     */
    public function handle(RequirementFinalizedEvent $event): void
    {
        $requirement = $event->requirement;

        // Aquí capturamos la lógica de envío de correos
        // Mail::to($requirement->requestingUnit->email)
        //     ->cc($requirement->functionalConsultant->person->email)
        //     ->send(new RequirementClosedMail($requirement));

        Log::info("✉️ [ASYNC] Notificación de cierre enviada para el Requerimiento RRTI: {$requirement->rrti}");
    }
}