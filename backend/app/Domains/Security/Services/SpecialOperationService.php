<?php

declare(strict_types=1);

namespace App\Domains\Security\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use App\Domains\Security\Models\SpecialCredential;
use App\Domains\Security\Models\User; 
use Exception;

class SpecialOperationService
{
    /**
     * FASE DE ONBOARDING: Configura el PIN por primera vez o lo resetea
     * validando la contraseña de inicio de sesión del usuario.
     */
    public function setupSpecialPin(string $userId, string $loginPassword, string $newPin): void
    {
        $user = User::findOrFail($userId);

        // Validar que el usuario realmente es quien dice ser (Doble Factor de sesión)
        if (!Hash::check($loginPassword, $user->password)) {
            throw new Exception("La contraseña de inicio de sesión es incorrecta.", 401);
        }

        // RN: El PIN no debe ser igual a la contraseña de login
        if ($loginPassword === $newPin) {
            throw new Exception("El PIN de operaciones no puede ser igual a tu contraseña.", 422);
        }

        // Crear o actualizar la credencial especial (Upsert)
        SpecialCredential::updateOrCreate(
            ['user_id' => $userId],
            [
                'pin_hash' => Hash::make($newPin),
                'failed_attempts' => 0,
                'is_locked' => false,
                'expires_at' => now()->addDays(90) // Caduca en 90 días
            ]
        );
    }

    /**
     * Validar la clave especial y emite un ticket en Redis con un TTL de 2 minutos.
     */
    public function issueDeletionTicket(string $userId, string $inputKey): string
    {
        $credential = SpecialCredential::where('user_id', $userId)->first();

        // Verificar si el usuario ya configuró su PIN alguna vez
        if (!$credential) {
            // Se manda un código 428 (Precondition Required) para que Angular sepa que debe abrir el modal de Onboarding
            throw new Exception("PIN_NOT_SETUP", 428); 
        }

        // Bloqueo de la clave de operaciones si se superan 3 intentos fallidos
        if ($credential->is_locked) {
            throw new Exception("Tu PIN ha sido bloqueado por seguridad tras múltiples intentos fallidos. Contacta al Administrador.", 423);
        }

        // Control de Caducidad (Rotación obligatoria)
        if ($credential->expires_at && $credential->expires_at->isPast()) {
            throw new Exception("PIN_EXPIRED", 426); // 426 (Upgrade Required) Angular pedirá rotación
        }

        // Validación criptográfica del PIN
        if (!Hash::check($inputKey, $credential->pin_hash)) {
            $credential->increment('failed_attempts');
            
            // Si llega a 3 errores, se bloquea la cuenta para operaciones críticas
            if ($credential->failed_attempts >= 3) {
                $credential->update(['is_locked' => true]);
                throw new Exception("PIN bloqueado. Has superado el máximo de 3 intentos fallidos.", 423);
            }

            $intentosRestantes = 3 - $credential->failed_attempts;
            throw new Exception("La Clave de Operaciones Especiales es incorrecta. Intentos restantes: {$intentosRestantes}", 401);
        }

        // Si el PIN es correcto, se resetea los intentos fallidos a 0
        if ($credential->failed_attempts > 0) {
            $credential->update(['failed_attempts' => 0]);
        }

        // Generar el Ticket (Token temporal único)
        $ticketId = bin2hex(random_bytes(16));
        $cacheKey = "delete_ticket_{$userId}_{$ticketId}";

        // Almacenar en Redis usando la Fachada Cache (TTL: 120 segundos)
        Cache::put($cacheKey, true, now()->addMinutes(2));

        return $ticketId;
    }

    /**
     * Consumir el ticket de Redis. Si no existe o expiró, lanza una excepción.
     */
    public function consumeDeletionTicket(string $userId, string $ticketId): void
    {
        $cacheKey = "delete_ticket_{$userId}_{$ticketId}";

        if (!Cache::pull($cacheKey)) {
            throw new Exception("El ticket de autorización ha expirado o es inválido.", 403);
        }
    }
}