<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Traits\HasUuid;

class RequirementCspePivot extends Pivot
{
    use HasUuid;

    protected $table = 'core.cspe_consultant_requirement';

    // ¡Aquí neutralizamos el bug de Laravel!
    public $incrementing = false;
    protected $keyType = 'string';
}