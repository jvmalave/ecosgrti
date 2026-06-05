<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'sometimes' permite que el campo no se envíe, pero si se envía, 'required' evita que venga nulo/vacío
            'description' => ['sometimes', 'required', 'string', 'min:10'],
            'requirement_type' => ['sometimes', 'required', 'string'],
            
            // RN-Validación de Cambios en Consultores: Mínimo 1 asignado
            'consultants' => ['sometimes', 'required', 'array', 'min:1'],
            'consultants.*' => ['uuid', 'exists:security.users,id'],
            
            // Protección contra inyección de archivos maliciosos
            'file_solicitud' => ['nullable', 'file', 'mimes:pdf', 'max:5120'], // Máx 5MB
            'file_planilla' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }
}