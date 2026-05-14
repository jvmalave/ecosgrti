<?php

namespace App\Domains\Security\Models;

use App\Traits\HasUuid;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasUuid; // Usamos el trait aquí

    protected $table = 'security.users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Métodos obligatorios para JWT
    public function getJWTIdentifier() { return $this->id; }
    public function getJWTCustomClaims() { return []; }
}