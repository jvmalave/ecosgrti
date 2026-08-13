<?php

declare(strict_types=1);

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimatedPhase extends Model
{
    use HasUuids;

    protected $table = 'core.estimated_phases';

    protected $fillable = [
        'schedule_estimation_id',
        'phase_name',
        'start_date',
        'end_date',
        'estimated_hours',
    ];

    /**
     * Casteo fundamental para garantizar que PHP y Laravel traten estos
     * campos con los tipos correctos (Carbon para fechas, float para horas).
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',       // Instancia de Carbon (Y-m-d)
            'end_date' => 'date',         // Instancia de Carbon (Y-m-d)
            'estimated_hours' => 'float', // Asegura cálculos matemáticos correctos
        ];
    }

    /**
     * Relación inversa hacia la cabecera de la estimación.
     */
    public function scheduleEstimation(): BelongsTo
    {
        return $this->belongsTo(ScheduleEstimation::class, 'schedule_estimation_id', 'id');
    }
}