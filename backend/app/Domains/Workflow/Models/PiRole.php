<?php

namespace App\Domains\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUuid;
use App\Domains\Core\Models\Requirement;



class PiRole extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'workflow.pi_roles';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'requirement_id',
        'requirement_role_id',
        'status',
        'is_approved',
        'created_by',
        'updated_by',
        'deleted_by',


    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    // ==========================================
    // RELACIONES
    // ==========================================
    
    public function requirement()
    {
        return $this->belongsTo(Requirement::class, 'requirement_id');
    }

    public function testUsers()
    {
        return $this->hasMany(PiTestUser::class, 'pi_role_id');
    }

    public function registers()
    {
        return $this->hasMany(PiRegister::class, 'pi_role_id');
    }

    public function functionalApproval()
    {
        // Relación 1 a 1
        return $this->hasOne(PiFunctionalApproval::class, 'pi_role_id');
    }
}