<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtRegister extends Model
{
    use HasFactory, HasUuids;

    /**
     * Define explícitamente la tabla y su esquema.
     *
     * @var string
     */
    protected $table = 'workflow.dt_registers';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'role_id',
        'title',
        'date',
        'description',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Relación: Un registro de bitácora técnica pertenece a un único rol de DT.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(DtRole::class, 'role_id');
    }
}