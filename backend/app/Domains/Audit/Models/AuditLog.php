<?php

namespace App\Domains\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use App\Domains\Security\Models\User; 

class AuditLog extends Model
{
    use HasUuid;

    protected $table = 'audit.audit_logs';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'payload',
        'target_id',
    ];

    /**
     * Mutaciones de atributos nativos.
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Usuario que ejecutó la acción.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}