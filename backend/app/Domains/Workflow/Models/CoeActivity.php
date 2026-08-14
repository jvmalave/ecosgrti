<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Security\Models\User;

class CoeActivity extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $table = 'workflow.coe_activities';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'coe_deliverable_id', 
        'title',
        'date',
        'description',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    /**
     * Relación Inversa con el Entregable en Construcción (COE)
     */
    public function coeDeliverable(): BelongsTo
    {
        return $this->belongsTo(CoeDeliverable::class, 'coe_deliverable_id');
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