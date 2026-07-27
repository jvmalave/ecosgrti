<?php

namespace App\Domains\Security\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FunctionalConsultant extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';

    protected $table = 'security.functional_consultants';

    protected $fillable = [
        'id',
        'person_id',
        'requesting_unit_id',
    ];

    public function sociedad()
    {
        return $this->belongsTo(Society::class, 'society_id');
    }

    public function sistema()
    {
        return $this->belongsTo(System::class, 'system_id');
    }

    public function unidad()
    {
        return $this->belongsTo(RequestingUnit::class, 'requesting_unit_id');
    }

    public function person()
    {
        return $this->belongsTo(\App\Domains\Security\Models\Person::class, 'person_id');
    }
    
}