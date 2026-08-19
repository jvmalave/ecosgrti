<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Core\Models\Requirement;

class CeeDeliverable extends Model
{
    use HasUuids;

    protected $table = 'workflow.cee_deliverables';

    protected $fillable = [
        'requirement_id', 
        'deliverable_id', 
        'ticket_id', 
        'status', 
        'rejection_reason', 
        'created_by', 
        'updated_by'
    ];

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class, 'deliverable_id');
    }

    public function ticket()
    {
        return $this->belongsTo(CeeTicket::class, 'ticket_id');
    }
}