<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Core\Models\Requirement;

class CspeConsultant extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;


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

    // Historial completo de requerimientos
    public function allRequirements()
    {
        return $this->belongsToMany(
            Requirement::class,
            'core.cspe_consultant_requirement', // pivote con esquema
            'cspe_consultant_id',
            'requirement_id'
        )->orderBy('created_at', 'desc');
    }

    // Requerimientos actualmente activos (Para la carga de trabajo)
    public function activeRequirements()
    {
        return $this->allRequirements()->where('status', '!=', 'RF'); 
    }
}