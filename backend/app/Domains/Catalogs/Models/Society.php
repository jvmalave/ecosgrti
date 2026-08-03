<?php

namespace App\Domains\Catalogs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Catalogs\Models\System;

class Society extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'catalogs.societies';

    protected $fillable = [
        'name',
        'acronym',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function systems(): HasMany
    {
        return $this->hasMany(System::class, 'society_id');
    }
}