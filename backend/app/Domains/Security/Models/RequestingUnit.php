<?php

namespace App\Domains\Security\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class RequestingUnit extends Model
{

  use HasUuid;
  use HasFactory;

  protected $table = 'requesting_units';

  // 1. Le decimos que NO es autoincremental
    public $incrementing = false;

    // 2. Le decimos que el ID es un texto (UUID)
    protected $keyType = 'string';
}
