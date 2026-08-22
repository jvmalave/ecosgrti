<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class PiTestResult extends Model
{
    use HasUuid;

    protected $table = 'workflow.pi_test_results';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pi_register_id',
        'test_user_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function register()
    {
        return $this->belongsTo(PiRegister::class, 'pi_register_id');
    }

    public function testUser()
    {
        return $this->belongsTo(PiTestUser::class, 'test_user_id');
    }
}