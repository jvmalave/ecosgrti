<?php

namespace App\Domains\Security\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Security\Observers\UserObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- Importación añadida

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasUuid, SoftDeletes; 
    use HasFactory; 

    protected $table = 'security.users';
    
    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'roles',
    ];

    protected $casts = [
        'roles' => 'array',
        'password' => 'hashed', 
        'password_updated_at' => 'datetime', 
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    /**
     * Relación inversa con Person.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'id', 'id');
    }

    /**
     * Relación: Un usuario tiene un historial de contraseñas.
     * Se ordena por fecha de creación descendente para facilitar la validación.
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class, 'user_id')->orderBy('created_at', 'desc');
    }

    // Métodos obligatorios para JWT
    public function getJWTIdentifier() { return $this->id; }
    public function getJWTCustomClaims() { return []; }
}