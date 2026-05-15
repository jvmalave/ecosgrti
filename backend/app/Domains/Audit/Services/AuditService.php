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
}