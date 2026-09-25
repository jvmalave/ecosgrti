<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PasswordSecurityService
{
    /**
     * Ejecuta el cambio de contraseña aplicando las reglas de seguridad e historial.
     *
     * @param User $user El usuario al que se le cambiará la clave.
     * @param string $currentPassword La contraseña actual sin encriptar.
     * @param string $newPassword La nueva contraseña sin encriptar.
     * @param string $actorId El UUID de quien realiza la acción (Auditoría).
     * @return void
     * 
     * @throws ValidationException
     */
    public function changeUserPassword(User $user, string $currentPassword, string $newPassword, string $actorId): void
    {
        // 1. Validar la contraseña actual
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual ingresada es incorrecta.']
            ]);
        }

        // 2. Prevenir que la nueva contraseña sea igual a la actual
        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['La nueva contraseña no puede ser idéntica a tu contraseña actual.']
            ]);
        }

        // 3. Validar contra el historial (últimas 2)
        $historicalPasswords = $user->passwordHistories()->take(2)->get();
        
        foreach ($historicalPasswords as $record) {
            if (Hash::check($newPassword, $record->password_hash)) {
                throw ValidationException::withMessages([
                    'new_password' => ['Por políticas de seguridad, no puedes utilizar ninguna de tus últimas 2 contraseñas.']
                ]);
            }
        }

        // 4. Ejecutar la actualización dentro de una transacción segura
        DB::transaction(function () use ($user, $newPassword, $actorId) {
            $newHash = Hash::make($newPassword);

            // Actualizamos el usuario (el reloj de 45 días se reinicia)
            $user->password = $newHash;
            $user->password_updated_at = now();
            $user->save();

            // Registramos la auditoría en el historial
            $user->passwordHistories()->create([
                'password_hash' => $newHash,
                'created_by' => $actorId,
            ]);
        });
    }
}