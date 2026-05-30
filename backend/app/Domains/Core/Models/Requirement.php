<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Security\Models\FunctionalConsultant;
use App\Domains\Security\Models\CspeConsultant;
use App\Domains\Core\Models\RequirementCspePivot;
use App\Traits\HasUuid;

class Requirement extends Model
{
    
    use HasUuid;
    use HasFactory;

    protected $table = 'core.requirements';

    // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';


    protected $fillable = [
        'id',
        'rrti', 
        'requirement_type',
        'creation_date',
        'description', 
        'management_type', 
        'needs_spreadsheet_path',
        'it_request_doc_path',
        'functional_consultant_id',
        'status', 
        'is_locked'
    ];

    /**
     * Relación: Un requerimiento pertenece a un Consultor Funcional
     */
    public function functionalConsultant()
    {
        // Apuntamos al modelo que ya creamos en el dominio de Seguridad
        return $this->belongsTo(FunctionalConsultant::class, 'functional_consultant_id');
    }

    public function cspeConsultants()
    {
        return $this->belongsToMany(
            CspeConsultant::class, 
            'core.cspe_consultant_requirement',
            'requirement_id',
            'cspe_consultant_id'
        )
        ->using(RequirementCspePivot::class) 
        ->withTimestamps(); 
    }
}