<?php

namespace App\Domains\Catalogs\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Domains\Security\Models\FunctionalConsultant;

class RequestingUnit extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'catalogs.requesting_units';

    protected $fillable = [
        'system_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class, 'system_id');
    }

    public function functionalConsultants(): HasMany
    {
        return $this->hasMany(FunctionalConsultant::class, 'unit_id');
    }
}