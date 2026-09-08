<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class AuditService
{
  protected array $sensitiveFields = [
    'password',
    'current_password',
    'new_password',
    'password_confirmation',
    'new_password_confirmation',
    'email',
  ];

  public function store(string $action, string $description, ?Request $request = null, ?string $userId = null): void
  {
    
    $payload = [];
    $targetId = null;

    if ($request) {
      $payload = $this->sanitizePayload($request->all());

      $targetId = $payload['target_id']
        ?? $payload['requirement_id']
        ?? $payload['record_id']
        ?? null;
    }

    AuditLog::create([
      'user_id'     => $userId ?? auth()->id(),
      'action'      => $action,
      'description' => $description,
      'target_id'   => $targetId,
      'ip_address'  => $request ? $request->ip() : request()->ip(),
      'user_agent'  => $request ? $request->userAgent() : request()->userAgent(),

      'payload'     => json_encode($payload),
    ]);
  }

  /**
   * Recorre el arreglo de datos y enmascara los valores de las claves sensibles.
   */
  protected function sanitizePayload(array $data): array
  {
    foreach ($data as $key => $value) {
      if (in_array(strtolower($key), $this->sensitiveFields)) {
        $data[$key] = '********';
      } elseif (is_array($value)) {
        $data[$key] = $this->sanitizePayload($value);
      }
    }
    return $data;
  }

  public function logModelChange(string $action, string $description, array $payload, ?string $userId = null, $targetId = null): void
  {
    $sanitizedPayload = $this->sanitizePayload($payload);
    $jsonPayload = json_encode($sanitizedPayload);

    $resolvedUserId = $userId ?? auth()->id() ?? ($payload['user_id'] ?? null);

    // Embudo de captura: prioriza el parámetro explícito, luego busca en el payload
    $resolvedTargetId = $targetId
      ?? $payload['target_id']
      ?? $payload['requirement_id']
      ?? $payload['record_id']
      ?? null;

    $log = AuditLog::create([
      'user_id'     => $resolvedUserId,
      'action'      => $action,
      'description' => $description,
      'target_id'   => $resolvedTargetId, // Asignación dinámica
      'ip_address'  => request()->ip(),
      'user_agent'  => request()->userAgent(),
      'payload'     => $jsonPayload,
    ]);

    Log::info("Auditoría de modelo guardada con ID: " . $log->id);
  }
}
