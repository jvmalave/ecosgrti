<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Core\Models\Requirement;

class CerRole extends Model
{
    use HasUuids;

    protected $table = 'workflow.cer_roles';

    protected $fillable = [
        'requirement_id', 
        'requirement_role_id', 
        'ticket_id', 
        'status', 
        'rejection_reason', 
        'rejection_history',
        'created_by', 
        'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'rejection_history' => 'array',
        ];
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function requirementRole()
    {
        return $this->belongsTo(RequirementRole::class, 'requirement_role_id');
    }

    public function ticket()
    {
        return $this->belongsTo(CerTicket::class, 'ticket_id');
    }
}