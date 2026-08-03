<?php

namespace App\Domains\Catalogs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Catalogs\Models\ProgressMatrixMilestone;


class ProgressMatrix extends Model
{
    use HasUuids;

    // RN-Aislamiento Total: Mapeo explícito al esquema correspondiente
    protected $table = 'catalogs.progress_matrices';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'management_type',
        'version_number',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version_number' => 'integer'
    ];

    /**
     * Relación 1:N hacia los hitos ponderados de esta versión de la matriz.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProgressMatrixMilestone::class, 'matrix_id');
    }

    
}