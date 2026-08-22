<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUuid;


class PiTestUser extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'workflow.pi_test_users';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'requirement_id',
        'pi_role_id',
        'identifier',
        'created_by',
        'updated_by',
        'deleted_by', 
    ];

    public function piRole()
    {
        return $this->belongsTo(PiRole::class, 'pi_role_id');
    }

    public function testResults()
    {
        return $this->hasMany(PiTestResult::class, 'test_user_id');
    }
}