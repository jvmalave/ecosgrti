<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Core\Models\Requirement;


class PapOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'workflow.pap_orders';

    protected $fillable = [
        'requirement_id',
        'order_number',
        'date',
        'file_path',
        'status',
        'result_category',
        'result_file',
        'created_by',
        'updated_by'
    ];

    public function requirement()
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    public function roles()
    {
        return $this->hasMany(PapRole::class, 'order_id');
    }
}