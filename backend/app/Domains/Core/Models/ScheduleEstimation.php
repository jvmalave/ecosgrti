<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use App\Domains\Security\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleEstimation extends Model
{
    use HasUuids;

    protected $table = 'core.schedule_estimations';

    protected $fillable = [
        'requirement_id',
        'created_by',
    ];

    /**
     * Relación inversa hacia el Requerimiento padre.
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id', 'id');
    }

    /**
     * Relación hacia el Usuario (Consultor) que realizó la estimación.
     * Cruza hacia el esquema de seguridad.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Relación 1:N hacia las 6 fases técnicas estimadas.
     */
    public function estimatedPhases(): HasMany
    {
        return $this->hasMany(EstimatedPhase::class, 'schedule_estimation_id', 'id');
    }
}