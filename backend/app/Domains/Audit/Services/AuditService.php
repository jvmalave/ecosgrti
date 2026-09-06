<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


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
    /**
     * Registra un evento en el esquema de auditoría.
     */
  //   public function store(string $action, string $description, Request $request, ?string $userId = null): void
  // {
  //     $data = [
  //         'user_id'     => $userId,
  //         'action'      => $action,
  //         'description' => $description,
  //         'ip_address'  => $request->ip(),
  //         'user_agent'  => $request->header('User-Agent'),
  //         'payload'     => json_encode($request->except(['password', 'password_confirmation'])),
  //     ];

  //     // Esto detendrá la ejecución y nos mostrará el objeto creado en Postman
  //     $log = AuditLog::create($data);
  //     Log::info("Auditoría guardada con ID: " . $log->id);
  // }

  public function store(string $action, string $description, ?Request $request = null, ?string $userId = null): void
    {
        try {
            $payload = [];

            if ($request) {
                // Sanitizamos el payload extraído del Request
                $payload = $this->sanitizePayload($request->all());
            }

            DB::table('audit.audit_logs')->insert([
                'user_id' => $userId ?? auth()->id(),
                'action' => $action,
                'description' => $description,
                'ip_address' => $request ? $request->ip() : request()->ip(),
                'user_agent' => $request ? $request->userAgent() : request()->userAgent(),
                'payload' => json_encode($payload),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("Fallo crítico en registro de auditoría: " . $e->getMessage());
        }
    }

    /**
     * Recorre el arreglo de datos y enmascara los valores de las claves sensibles.
     */
    protected function sanitizePayload(array $data): array
    {
        foreach ($data as $key => $value) {
            // Si la clave actual coincide con uno de los campos sensibles
            if (in_array(strtolower($key), $this->sensitiveFields)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                // Si el valor es otro arreglo, aplicamos recursividad
                $data[$key] = $this->sanitizePayload($value);
            }
        }
        return $data;
    }
  
  
  /**
     * Registra un evento automático de cambio de modelo.
     */
    // public function logModelChange(string $action, string $description, array $payload, ?string $userId = null, $targetId = null): void
    // {
    // //      1. Forzamos la conversión a String puro ANTES de tocar el modelo
    //     $jsonPayload = json_encode($payload);

    //     $userId = auth()->id() ?? ($payload['user_id'] ?? null);

    // //      2. Guardamos pasando exclusivamente strings
    //     $log = AuditLog::create([
    //         'user_id'     => $userId,
    //         'action'      => $action,
    //         'description' => $description,
    //         'target_id'   => $payload['record_id'] ?? null, 
    //         'ip_address'  => request()->ip(), 
    //         'user_agent'  => request()->userAgent(),
    //         'payload'     => $jsonPayload, 
    //     ]);

    //     Log::info("Auditoría de modelo guardada con ID: " . $log->id);
    // }

    public function logModelChange(string $action, string $description, array $payload, ?string $userId = null, $targetId = null): void
    {
        // 1. Sanitizamos el payload para ocultar contraseñas o datos sensibles
        $sanitizedPayload = $this->sanitizePayload($payload);

        // 2. Forzamos la conversión a String puro ANTES de tocar el modelo
        $jsonPayload = json_encode($sanitizedPayload);

        $userId = auth()->id() ?? ($payload['user_id'] ?? null);

        // 3. Guardamos pasando exclusivamente strings
        $log = AuditLog::create([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'target_id'   => $payload['record_id'] ?? null, 
            'ip_address'  => request()->ip(), 
            'user_agent'  => request()->userAgent(),
            'payload'     => $jsonPayload, 
        ]);

        Log::info("Auditoría de modelo guardada con ID: " . $log->id);
    }

    
}