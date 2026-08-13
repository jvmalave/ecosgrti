<?php

namespace App\Domains\Catalogs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressMatrixMilestone extends Model
{
    use HasUuids;

    protected $table = 'catalogs.progress_matrix_milestones';

    protected $fillable = [
        'matrix_id',
        'milestone_id',
        'weight_percentage'
    ];

    protected $casts = [
        // RN-Precisión Decimal: Casteo explícito a float/decimal para cálculos
        'weight_percentage' => 'decimal:2'
    ];

    /**
     * Relación inversa hacia la matriz padre.
     */
    public function matrix(): BelongsTo
    {
        return $this->belongsTo(ProgressMatrix::class, 'matrix_id');
    }

    public function milestone()
    {
        // Asegúrate de importar el modelo base correcto en la cabecera si es necesario.
        // Asumiendo que tu modelo maestro se llama Milestone:
        return $this->belongsTo(Milestone::class, 'milestone_id');
    }
}