<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuRole extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'workflow.au_roles';

    protected $guarded = [];

    protected $casts = [
        'rejection_history' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AuTicket::class, 'ticket_id');
    }
    
    public function requirementRole()
    {
        return $this->belongsTo(\App\Domains\Workflow\Models\RequirementRole::class, 'requirement_role_id');
    }
}