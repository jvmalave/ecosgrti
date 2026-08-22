<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUuid;

class PiRegister extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'workflow.pi_registers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'requirement_id',
        'pi_role_id',
        'title',
        'date',
        'description',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function piRole()
    {
        return $this->belongsTo(PiRole::class, 'pi_role_id');
    }

    public function testResults()
    {
        return $this->hasMany(PiTestResult::class, 'pi_register_id');
    }
}