<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Core\Models\Requirement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DtRole extends Model
{
    use HasFactory, HasUuids;

    /**
     * Define explícitamente la tabla y su esquema.
     *
     * @var string
     */
    protected $table = 'workflow.dt_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'requirement_id',
        'requirement_role_id',
        'name',
        'status',
    ];

    /**
     * Relación: Pertenece al requerimiento principal (Core)
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    /**
     * Relación: Proviene del Rol mapeado en ATF (Workflow)
     */
    public function requirementRole(): BelongsTo
    {
        return $this->belongsTo(RequirementRole::class, 'requirement_role_id');
    }

    /**
     * Relación: Posee múltiples registros de bitácora técnica
     */
    public function registers(): HasMany
    {
        return $this->hasMany(DtRegister::class, 'role_id');
    }
}