<?php

namespace App\Domains\Security\Services;

use Illuminate\Support\Facades\Cache;

class AuthService
{
    // Definimos las constantes de seguridad
    private const MAX_ATTEMPTS = 3;
    private const LOCKOUT_TIME = 900; // 15 minutos en segundos

    /**
     * Verifica si el usuario está bloqueado en Redis.
     */
    public function isLockedOut(string $username): bool
    {
        $attempts = Cache::get("attempts_{$username}", 0);
        return $attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Incrementa el contador de fallos.
     */
    public function incrementAttempts(string $username): int
    {
        $key = "attempts_{$username}";
        $attempts = Cache::get($key, 0) + 1;
        
        Cache::put($key, $attempts, self::LOCKOUT_TIME);
        
        return $attempts;
    }

    /**
     * Limpia los intentos tras un login exitoso.
     */
    public function resetAttempts(string $username): void
    {
        Cache::forget("attempts_{$username}");
    }
}