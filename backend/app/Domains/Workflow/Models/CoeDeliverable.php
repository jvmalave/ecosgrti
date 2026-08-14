<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User;


class CoeDeliverable extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'workflow.coe_deliverables';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'req_id',
        'deliverable_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
    

    /**
     * Relación con el Requerimiento Maestro
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'req_id');
    }

    /**
     * Relación con el Entregable Original (ATF)
     */
    public function masterDeliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'deliverable_id', 'id');
    }

    /**
     * Relación con la Bitácora Transaccional (1:N)
     */
    public function registers(): HasMany
    {
        // Nota: Asegúrate de que la clave foránea coincida con el nombre que le diste en la migración
        return $this->hasMany(CoeActivity::class, 'coe_deliverable_id', 'id');
    }

    /**
     * Trazabilidad Forense (Auditoría)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}