<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Domains\Core\Models\Requirement;
use Illuminate\Validation\Rule;

class StoreEstimationRequest extends FormRequest
{
    /**
     * La autorización la maneja nuestro RoleMiddleware en las rutas.
     */
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            // El requerimiento debe existir y ser un UUID válido
            'requirement_id' => ['required', 'uuid', Rule::exists(Requirement::class, 'id')],
            
            // Obligamos a que vengan exactamente 6 fases
            'phases' => ['required', 'array', 'size:6'],
            
            // Validaciones por cada fase dentro del array
            'phases.*.phase_name' => ['required', 'string', 'in:ATF,DISENO,CONSTRUCCION,PRUEBAS,CERTIFICACION,IMPLEMENTACION'],
            'phases.*.start_date' => ['required', 'date'],
            
            // RN-Validación de Rango Interno: end_date >= start_date
            'phases.*.end_date' => ['required', 'date', 'after_or_equal:phases.*.start_date'],
            
            // Horas estimadas sin valores negativos
            'phases.*.estimated_hours' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes personalizados para el frontend (Opcional pero recomendado)
     */
    public function messages(): array
    {
        return [
            'phases.size' => 'Debe registrar la estimación para las 6 fases del ciclo de vida.',
            'phases.*.end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio en ninguna fase.',
        ];
    }
}