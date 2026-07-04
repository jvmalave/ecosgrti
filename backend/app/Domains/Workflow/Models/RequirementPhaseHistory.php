<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\Core\Models\Requirement;

class RequirementPhaseHistory extends Model
{
    use HasUuids;

    protected $table = 'workflow.requirement_phase_history';

    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';

    protected $fillable = [
        'requirement_id',
        'phase_status_code',
        'transitioned_at',
        'executed_by_user_id',
        'remarks'
    ];

    protected $casts = [
        'transitioned_at' => 'datetime',
    ];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }
}