<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Core\Models\Requirement;



class CerTicket extends Model
{
    use HasUuids;

    protected $table = 'workflow.cer_tickets';

    protected $fillable = [
        'requirement_id', 
        'ticket_number', 
        'request_date', 
        'file_path', 
        'status', 
        'result_category', 
        'result_file', 
        'created_by', 
        'updated_by'
    ];

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }

    public function roles()
    {
        return $this->hasMany(CerRole::class, 'ticket_id');
    }
}