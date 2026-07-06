<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\RequirementRole;

class StoreRequirementRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización (Gatekeeper) la manejaremos en el Controlador/Middleware
    }
    public function rules(): array
    {
        // Determinamos si es un POST (creación) o PUT (actualización)
        if ($this->isMethod('post')) {
            $requirementId = $this->route('requirementId');
            $roleId = null;
        } else {
            // En PUT, extraemos el ID del rol de la URL (ajusta 'id' si tu parámetro se llama 'role' o 'roleId' en api.php)
            $roleId = $this->route('roleId') ?? $this->route('id') ?? $this->route('role');
            
            // Buscamos el rol en base de datos para saber a qué requerimiento pertenece
            $role = RequirementRole::find($roleId);
            $requirementId = $role ? $role->requirement_id : null;
        }

        return [
            'role_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(RequirementRole::class, 'role_name')
                    ->where('requirement_id', $requirementId)
                    ->whereNull('deleted_at')
                    ->ignore($roleId), // Ignoramos el rol actual
            ],
            'description' => 'required|string|min:10|max:500',
            'assignment_type' => 'required|string|max:50',
        ];
    }

    public function message(): array
    {
        return [
            'role_name.required' => 'El nombre del rol es obligatorio.',
            'role_name.unique' => 'Este rol ya está asignado a este requerimiento.',
            'description.max' => 'La descripción no puede exceder los 500 caracteres.',
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
            'assignment_type.required' => 'El tipo de asignación es obligatorio.',

        ];
    }
}