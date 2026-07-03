<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class AuditService
{
    /**
     * Registra un evento en el esquema de auditoría.
     */
    public function store(string $action, string $description, Request $request, ?string $userId = null): void
  {
      $data = [
          'user_id'     => $userId,
          'action'      => $action,
          'description' => $description,
          'ip_address'  => $request->ip(),
          'user_agent'  => $request->header('User-Agent'),
          'payload'     => json_encode($request->except(['password', 'password_confirmation'])),
      ];

      // Esto detendrá la ejecución y nos mostrará el objeto creado en Postman
      $log = AuditLog::create($data);
      Log::info("Auditoría guardada con ID: " . $log->id);; 
  }

  /**
     * Registra un evento automático de cambio de modelo.
     */
    public function logModelChange(string $action, string $description, array $payload, ?string $userId = null): void
    {
        // 1. Forzamos la conversión a String puro ANTES de tocar el modelo
        $jsonPayload = json_encode($payload);

        // 2. Guardamos pasando exclusivamente strings
        $log = AuditLog::create([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => request()->ip(), 
            'user_agent'  => request()->userAgent(), // 🟢 Más seguro que header()
            'payload'     => $jsonPayload,           // 🟢 El string ya procesado
        ]);

        Log::info("Auditoría de modelo guardada con ID: " . $log->id);
    }
}