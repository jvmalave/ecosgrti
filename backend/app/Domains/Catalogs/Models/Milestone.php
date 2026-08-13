<?php

namespace App\Domains\Catalogs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Milestone extends Model
{
    use HasUuids;

    
    // DEFINICION DE LA CONEXION Y TABLA APUNTANDO AL ESQUEMA MULTIESQUEMA POSTGRESQL
    protected $connection = 'pgsql';
    protected $table = 'catalogs.milestones';

    
    // CLAVE PRIMARIA DE TIPO UUID NO AUTOINCREMENTAL
    protected $keyType = 'string';
    public $incrementing = false;

    // ASIGNACION MASIVA PROTEGIDA
    protected $fillable = [
        'id',
        'phase',
        'phase_code',
        'name',
        'status_code',
        'default_weight',
        'management_type',
        'sort_order'
    ];

    
    // CASTING DE TIPOS PARA GARANTIZAR INTEGRIDAD EN LA CAPA DE SERVICIOS
    protected $casts = [
        'default_weight' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    
}