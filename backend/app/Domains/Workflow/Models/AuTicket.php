<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class AuTicket extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'workflow.au_tickets';

    protected $guarded = [];

    protected $casts = [
        'request_date' => 'date',
    ];

    public function roles(): HasMany
    {
        return $this->hasMany(AuRole::class, 'ticket_id');
    }
}