<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Security\Models\FunctionalConsultant;
use App\Domains\Security\Models\CspeConsultant;
use App\Domains\Core\Models\RequirementCspePivot;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Core\Models\ScheduleEstimation;
use App\Domains\Workflow\Models\RequirementPhaseHistory;
use App\Domains\Catalogs\Models\ProgressMatrix;

class Requirement extends Model
{
    
    use HasUuid;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'core.requirements';

    // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';

    protected $with = ['progressMatrix'];


    protected $fillable = [
      'id',
      'rrti', 
      'requirement_type',
      'creation_date',
      'description', 
      'management_type',
      'progress_matrix_id',  
      'needs_spreadsheet_path',
      'it_request_doc_path',
      'functional_consultant_id',
      'status', 
      'is_locked',
      'snapshot_society_name',
      'snapshot_system_name',
      'snapshot_unit_name',
      'progress_percentage'
    ];


    protected static function newFactory()
    {
        return \Database\Factories\Core\RequirementFactory::new(); 
    }

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

    /**
     * Relación 1 a 1: Un Requerimiento tiene una (y solo una) Estimación de Cronograma (Camino de Hierro).
     * * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function scheduleEstimation()
    {
        return $this->hasOne(ScheduleEstimation::class, 'requirement_id');
    }

    public function phaseHistories()
    {
        return $this->hasMany(RequirementPhaseHistory::class, 'requirement_id');
    }

    
    public function progressMatrix()
    {
        return $this->belongsTo(ProgressMatrix::class, 'progress_matrix_id');
    }
}