<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MilestoneRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        return true; // La seguridad perimetral se maneja mediante el middleware 'auth:api' y 'role:admin'
    }

    /**
     * Reglas de validación para las operaciones Store y Update.
     */
    public function rules(): array
    {
        return [
            'phase'           => ['required', 'string', 'max:255'],
            'phase_code'      => ['required', 'string', 'max:50'],
            'name'            => ['required', 'string', 'max:255'],
            'status_code'     => ['required', 'string', 'max:50'],
            'default_weight'  => ['required', 'numeric', 'min:0'],
            'management_type' => ['required', 'string', 'in:ROLES,ENTREGABLES,MIXTO'],
            'sort_order'      => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Mensajes de validación personalizados.
     */
    public function messages(): array
    {
        return [
            'management_type.in' => 'El tipo de gestión debe ser "ROLES", "ENTREGABLES" o "MIXTO".',
            'management_type.required' => 'El tipo de gestión es obligatorio.',
            'phase.required' => 'La fase es obligatoria.',
            'phase_code.required' => 'El código de fase es obligatorio.',
            'name.required' => 'El nombre es obligatorio.',
            'status_code.required' => 'El código de estado es obligatorio.',
            'default_weight.required' => 'El peso es obligatorio.',
            'sort_order.required' => 'El orden es obligatorio.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.min' => 'El orden debe ser un número positivo.',
        ];
    }
}