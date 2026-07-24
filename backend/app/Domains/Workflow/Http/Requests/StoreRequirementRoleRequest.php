<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\RequirementRole;
use Illuminate\Support\Facades\Log;

class StoreRequirementRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización de perfil se manejará en Middleware/Policy
    }

    /**
     * Sanitización de entradas para prevenir ataques XSS (Cross-Site Scripting).
     * Se ejecuta antes de aplicar las reglas de validación.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'role_name' => $this->role_name ? strip_tags(trim($this->role_name)) : null,
            'description' => $this->description ? strip_tags(trim($this->description)) : null,
            'assignment_type' => $this->assignment_type ? trim($this->assignment_type) : null,
        ]);
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            $requirementId = $this->route('requirementId');
            $roleId = null;
        } else {
            // Actualización
            $roleId = $this->route('roleId');
            $role = RequirementRole::find($roleId);
            $requirementId = $role ? $role->requirement_id : null;
        }

        Log::info('Validando unicidad:', [
        'role_name' => $this->role_name,
        'requirement_id' => $requirementId,
        'role_id' => $roleId
    ]);

        return [
            'role_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(RequirementRole::class, 'role_name')
                ->where('requirement_id', $requirementId)
                ->whereNull('deleted_at') 
                ->ignore($roleId, 'id'), 
                ],

            
            'description' => 'required|string|min:10|max:500',
            'assignment_type' => 'required|string|max:50',
        ];
    }

    public function messages(): array
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