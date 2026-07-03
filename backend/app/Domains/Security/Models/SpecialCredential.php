<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialCredential extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'security.special_credentials';

    protected $fillable = [
        'user_id',
        'pin_hash',
        'failed_attempts',
        'expires_at',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'expires_at' => 'datetime',
        'failed_attempts' => 'integer',
    ];

    // Ocultar el hash al serializar por seguridad
    protected $hidden = [
        'pin_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}