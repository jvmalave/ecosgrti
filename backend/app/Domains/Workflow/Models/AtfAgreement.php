<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Core\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\Workflow\AtfAgreementFactory;

class AtfAgreement extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    // Especificamos la tabla con su esquema
    protected $table = 'workflow.atf_agreements';

    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';

    protected static function newFactory()
{
    // Ajusta esta ruta a donde realmente esté tu clase Factory
    return AtfAgreementFactory::new();
}

    protected $fillable = [
        'requirement_id',
        'agreement_date',
        'description',
        'registered_by_user_id'
    ];

    protected $casts = [
        'agreement_date' => 'date',
    ];

    /**
     * Relación con el requerimiento padre.
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }
}