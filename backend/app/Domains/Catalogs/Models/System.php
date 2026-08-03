<?php

namespace App\Domains\Catalogs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Catalogs\Models\RequestingUnit;

class System extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'catalogs.systems';

    protected $fillable = [
        'society_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class, 'society_id');
    }

    public function requestingUnits(): HasMany
    {
        return $this->hasMany(RequestingUnit::class, 'system_id');
    }
}