<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class CspeConsultant extends Model
{
    use HasFactory;
    use HasUuid;


    protected $table = 'security.cspe_consultants';

    // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'person_id',
    ];

    // Relación con la Persona (Trae el nombre, apellido, correo, etc.)
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}