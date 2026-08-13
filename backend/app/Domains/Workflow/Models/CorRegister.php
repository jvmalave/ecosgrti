<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorRegister extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'workflow.cor_registers';

    protected $fillable = [
        'role_id',
        'title',
        'date',
        'description',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(CorRole::class, 'role_id');
    }
}