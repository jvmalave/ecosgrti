<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends Model
{
    use HasUuids;

    protected $table = 'security.password_histories';

    protected $fillable = [
        'user_id',
        'password_hash',
        'created_by', 
    ];

    /**
     * Relación: Un historial pertenece al usuario dueño de la cuenta.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación (Auditoría): El usuario que ejecutó el cambio de clave.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}