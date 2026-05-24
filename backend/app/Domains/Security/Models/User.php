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


#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasUuid, SoftDeletes; 
    use HasFactory; 


    protected $table = 'security.users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $casts = [
    'roles' => 'array',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    // Métodos obligatorios para JWT
    public function getJWTIdentifier() { return $this->id; }
    public function getJWTCustomClaims() { return []; }
}