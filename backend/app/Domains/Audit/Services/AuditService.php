<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Registra un evento en el esquema de auditoría.
     */
    public function store(string $action, string $description, Request $request, ?string $userId = null): void
    {
        AuditLog::create([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->header('User-Agent'),
            'payload'     => json_encode($request->except(['password', 'password_confirmation'])), // Seguridad: No guardamos la clave
        ]);
    }
}