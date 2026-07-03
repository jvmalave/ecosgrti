<?php

namespace App\Domains\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid; // El trait que creamos para la US02


class AuditLog extends Model
{
    use HasUuid;

    // Indicamos explícitamente el esquema y la tabla
    protected $table = 'audit.audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'payload' // Para guardar datos extra en JSON si es necesario
    ];

    

    // Desactivamos timestamps si prefieres manejar solo 'created_at' 
    // o déjalos si la migración los tiene.
}