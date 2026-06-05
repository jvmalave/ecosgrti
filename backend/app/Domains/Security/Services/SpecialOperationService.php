<?php

declare(strict_types=1);

namespace App\Domains\Security\Services;

use Illuminate\Support\Facades\Cache;
//use Illuminate\Support\Facades\Hash;
use Exception;

class SpecialOperationService
{
  /**
   * Valida la clave especial y emite un ticket en Redis con un TTL de 2 minutos.
   *
   * @param string $userId
   * @param string $inputKey
   * @return string El ID del ticket (Token temporal)
   * @throws Exception
   */
  public function issueDeletionTicket(string $userId, string $inputKey): string
  {
    // 1. Validar la clave contra la base de datos (security.special_credentials)
    // Por motivos de agilidad en este sprint, si aún no tienes esa tabla, 
    // simularemos la validación contra una variable de entorno o la contraseña del usuario.
    // Lo ideal aquí es: $credential = SpecialCredential::where('user_id', $userId)->first();

    $masterKey = config('app.special_operations_key', 'CANTV_CSPE_2026*'); // Fallback seguro

    if ($inputKey !== $masterKey) {
      // Si tuvieras un Hash: if (!Hash::check($inputKey, $credential->encrypted_key))
      throw new Exception("La Clave de Operaciones Especiales es incorrecta.");
    }

    // 2. Generar el Ticket (Token temporal único)
    $ticketId = bin2hex(random_bytes(16)); // Genera un string seguro de 32 caracteres
    $cacheKey = "delete_ticket_{$userId}_{$ticketId}";

    // 3. Almacenar en Redis usando la Fachada Cache (TTL: 120 segundos = 2 minutos)
    // Esto cumple exactamente con la directiva del diagrama de secuencia
    Cache::put($cacheKey, true, now()->addMinutes(2));

    return $ticketId;
  }

  /**
   * Consume el ticket de Redis. Si no existe o expiró, lanza una excepción.
   * El método 'pull' de la fachada Cache lo lee y lo elimina atómicamente.
   */
  public function consumeDeletionTicket(string $userId, string $ticketId): void
  {
    $cacheKey = "delete_ticket_{$userId}_{$ticketId}";

    if (!Cache::pull($cacheKey)) {
      throw new Exception("El ticket de autorización ha expirado, es inválido o ya fue utilizado.");
    }
  }
}
