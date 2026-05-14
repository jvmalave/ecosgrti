<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot del trait para generar el UUID automáticamente al crear el registro.
     */
    protected static function bootHasUuid()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Desactivar el incremento automático para este modelo.
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Especificar que el tipo de la llave primaria es un string.
     */
    public function getKeyType()
    {
        return 'string';
    }
}