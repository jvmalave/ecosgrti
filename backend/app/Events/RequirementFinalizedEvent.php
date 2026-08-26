<?php

declare(strict_types=1);

namespace App\Events;

use App\Domains\Core\Models\Requirement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RequirementFinalizedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * El requerimiento que acaba de ser cerrado.
     */
    public readonly Requirement $requirement;

    /**
     * Crea una nueva instancia del evento.
     */
    public function __construct(Requirement $requirement)
    {
        $this->requirement = $requirement;
    }
}