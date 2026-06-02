<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasUuid;

class Society extends Model
{

    use HasFactory;
    use HasUuid;

    protected $table = 'security.societies';

    // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';
}

