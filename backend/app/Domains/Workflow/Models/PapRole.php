<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Core\Models\Requirement;

class PapRole extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'workflow.pap_roles';

    protected $fillable = [
        'requirement_id',
        'requirement_role_id',
        'order_id',
        'status',
        'fail_reason',
        'rejection_history',
        'created_by',
        'updated_by'
    ];

    // 🟢 EL DETALLE VITAL: Casteo a array para que Angular pueda leer la línea de tiempo
    protected $casts = [
        'rejection_history' => 'array',
    ];

    public function requirement()
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    public function requirementRole()
    {
        // Asegúrate de importar el modelo RequirementRole en la cabecera si es necesario
        return $this->belongsTo(RequirementRole::class, 'requirement_role_id'); 
    }

    public function order()
    {
        return $this->belongsTo(PapOrder::class, 'order_id');
    }
}