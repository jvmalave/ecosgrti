<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuid;

class System extends Model

{
    use HasFactory;
    use HasUuid;
    
    protected $table = 'security.systems';

    // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';
}