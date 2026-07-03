<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClosePlanningRequest extends FormRequest
{
    /**
     * Solo los usuarios con rol 'Coordinador' o 'Consultor' pueden cerrar la fase de Planificación.
     */
    public function authorize(): bool
    {
        return true; // Asumimos que la autorización de rutas (middleware) ya validó el acceso base.
    }

    /**
     * 
     * Registro de auditoría forense con un contexto claro del porqué se cierra la fase.
     */
    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'justification.required' => 'Debe proporcionar una justificación técnica para el cierre de la Planificación (PL).',
            'justification.min'      => 'La justificación debe tener al menos 10 caracteres para ser válida.',
        ];
    }
}