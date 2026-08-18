<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUuid;

class PiFunctionalApproval extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'workflow.pi_functional_approvals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pi_role_id',
        'approver_name',
        'date',
        'file_path',
        'original_name',
        'file_size',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    

    protected $casts = [
        'date' => 'date',
        'file_size' => 'integer',
    ];

    public function piRole()
    {
        return $this->belongsTo(PiRole::class, 'pi_role_id');
    }
}