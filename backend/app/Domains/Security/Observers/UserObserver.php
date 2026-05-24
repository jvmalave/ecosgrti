<?php

namespace App\Domains\Security\Observers;

use App\Domains\Security\Models\User;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    protected AuditService $auditService;

    // Inyectamos tu servicio automáticamente gracias a Laravel
    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Se ejecuta automáticamente DESPUÉS de que un usuario es actualizado en BD.
     */
    public function updated(User $user): void
    {
        // Verificamos si el campo específico "roles" fue alterado
        if ($user->wasChanged('roles')) {
            
            // Construimos el arreglo con los datos exactos que pide tu Gherkin
            $payload = [
                'subject_id' => $user->id,
                'old_roles'  => $user->getOriginal('roles'),
                'new_roles'  => $user->roles,
            ];

            // Delegamos el guardado a tu dominio de Auditoría
            $this->auditService->logModelChange(
                action: 'ROLE_UPDATED',
                description: "Se modificaron los privilegios del usuario {$user->email}",
                payload: $payload,
                userId: Auth::id() // El UUID del admin que hizo el cambio
            );
        }
    }
}