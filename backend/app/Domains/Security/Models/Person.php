<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasUuid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{

  use HasUuid;
  use HasFactory;
  use SoftDeletes;

  
  protected $table = 'security.persons';

  protected $fillable = [
        'id', // Recomendado al usar UUIDs
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

  // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';


    /**
     * Relación 1:1 con el modelo User (Esquema Security).
     * Permite empaquetar las credenciales de acceso junto a la identidad base.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'id');
    }

    /**
     * Relación 1:1 con el modelo FunctionalConsultant.
     */
    public function functionalConsultant(): HasOne
    {
        return $this->hasOne(FunctionalConsultant::class, 'person_id', 'id');
    }

    /**
     * Relación 1:1 con el modelo CspeConsultant.
     */
    public function cspeConsultant(): HasOne
    {
        return $this->hasOne(CspeConsultant::class, 'person_id', 'id');
    }
  
}
