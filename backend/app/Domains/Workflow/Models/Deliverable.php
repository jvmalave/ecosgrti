<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use App\Domains\Core\Models\Requirement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Database\Factories\Workflow\DeliverableFactory;

class Deliverable extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'workflow.deliverables';
    

    protected $fillable = [
        'requirement_id',
        'name',
        'description',
    ];

    /**
     * Define explícitamente la fábrica para este modelo.
     */
    protected static function newFactory()
    {
        return DeliverableFactory::new();
    }

    /**
     * Relación con el requerimiento principal (Core)
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }
}