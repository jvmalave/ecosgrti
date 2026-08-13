<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorRole extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'workflow.cor_roles';
    
    protected $fillable = [
        'requirement_role_id',
        'requirement_id',
        'name',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function registers(): HasMany
    {
        return $this->hasMany(CorRegister::class, 'role_id');
    }
}