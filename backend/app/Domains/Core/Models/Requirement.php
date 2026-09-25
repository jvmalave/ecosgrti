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
use App\Domains\Workflow\Models\CerTicket;
use App\Domains\Workflow\Models\PapOrder;
use App\Domains\Workflow\Models\AuTicket;
use App\Domains\Workflow\Models\CeeTicket;

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

  
  protected $appends = ['frozen_phases'];

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
    'progress_percentage',
    'notification_date',
    'completion_date',
    'closure_act_path',
    'notification_support_path',
    'conformity_declaration'
  ];

  protected static function newFactory()
  {
    return \Database\Factories\Core\RequirementFactory::new();
  }

    // =========================================================================
    // ACCESORES Y MUTADORES (LÓGICA ESTRUCTURAL)
    // =========================================================================

  /**
   * Calcula las fases inmutables leyendo directamente el historial de eventos,
   * garantizando soporte perfecto para topologías concurrentes (Mixto).
   */
  public function getFrozenPhasesAttribute(): array
  {
    // 1. Cierre Global Absoluto
    if ($this->status === 'RC') {
      $all = ['ATF', 'DT', 'COR', 'COE', 'PI', 'CER', 'CEE', 'PAP', 'AU'];
      if ($this->management_type === 'Roles') return array_values(array_diff($all, ['COE', 'CEE']));
      if ($this->management_type === 'Entregables') return array_values(array_intersect($all, ['ATF', 'COE', 'CEE']));
      return $all;
    }

    $frozen = [];

    // 2. Extraer del historial (Event Sourcing)
    $histories = $this->relationLoaded('phaseHistories')
      ? $this->phaseHistories
      : $this->phaseHistories()->get();

    foreach ($histories as $history) {
      // Extrae las fases inmutables a partir del phase_status_code
      if (str_ends_with($history->phase_status_code, '-C')) {
        // Separa 'DT-C' y nos quedamos con 'DT'
        $parts = explode('-', $history->phase_status_code);
        if (isset($parts[0])) {
          $frozen[] = $parts[0];
        }
      }
    }

    // 3. Fallback en tiempo real para el status maestro actual
    if (str_ends_with($this->status, '-C')) {
      $parts = explode('-', $this->status);
      if (isset($parts[0])) {
        $frozen[] = $parts[0];
      }
    }

    return array_values(array_unique($frozen));
  }

    // =========================================================================
    // RELACIONES
    // =========================================================================

  /**
   * Relación: Un requerimiento pertenece a un Consultor Funcional
   */
  public function functionalConsultant()
  {
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

  /**
     * Relación 1 a N: Un requerimiento tiene muchos acuerdos ATF.
     */
    public function atfAgreements()
    {
        // Viendo tu barra lateral, el modelo AtfAgreement está en el dominio Workflow
        return $this->hasMany(\App\Domains\Workflow\Models\AtfAgreement::class, 'requirement_id');
    }

    /**
     * Relación 1 a N: Un requerimiento tiene muchos roles.
     */
    public function roles()
    {
        return $this->hasMany(\App\Domains\Workflow\Models\RequirementRole::class, 'requirement_id');
    }

    // ==========================================
    // RELACIONES PARA TRAZABILIDAD DE FASES
    // ==========================================
    
    public function dtRoles() { return $this->hasMany(\App\Domains\Workflow\Models\DtRole::class, 'requirement_id'); }
    public function corRoles() { return $this->hasMany(\App\Domains\Workflow\Models\CorRole::class, 'requirement_id'); }
    public function piRoles() { return $this->hasMany(\App\Domains\Workflow\Models\PiRole::class, 'requirement_id'); }
    public function cerRoles() { return $this->hasMany(\App\Domains\Workflow\Models\CerRole::class, 'requirement_id'); }
    public function papRoles() { return $this->hasMany(\App\Domains\Workflow\Models\PapRole::class, 'requirement_id'); }
    public function auRoles() { return $this->hasMany(\App\Domains\Workflow\Models\AuRole::class, 'requirement_id'); }

    public function coeDeliverables() 
    { 
        return $this->hasMany(\App\Domains\Workflow\Models\CoeDeliverable::class, 'req_id'); 
    }
    
    public function ceeDeliverables() 
    { 
        return $this->hasMany(\App\Domains\Workflow\Models\CeeDeliverable::class, 'requirement_id'); 
    }

    /**
     * Relación 1 a N: Un requerimiento tiene muchos entregables.
     */
    public function deliverables()
    {
        // Ajusta la ruta del modelo de tu entregable
        return $this->hasMany(\App\Domains\Workflow\Models\Deliverable::class, 'requirement_id');
    }

    /**
     * Relación 1 a 1: Un requerimiento tiene un ticket de certificación de roles (CSAL).
     */
    public function cerTicket()
    {
        return $this->hasOne(CerTicket::class, 'requirement_id');
    }

    /**
     * Relación 1 a 1: Un requerimiento tiene una orden de pase a producción.
     */
    public function papOrder()
    {
        return $this->hasOne(PapOrder::class, 'requirement_id');
    }

    /**
     * Relación 1 a 1: Un requerimiento tiene un ticket de asignación de usuarios (CSAL).
     */
    public function auTicket()
    {
        return $this->hasOne(AuTicket::class, 'requirement_id');
    }

    /**
     * Relación 1 a 1: Un requerimiento tiene un ticket de certificación de entregables (CEE).
     */
    public function ceeTicket()
    {
        return $this->hasOne(CeeTicket::class, 'requirement_id');
    }

    
}
