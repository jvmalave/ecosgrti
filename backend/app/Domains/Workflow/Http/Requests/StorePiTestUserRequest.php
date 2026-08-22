<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePiTestUserRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta petición.
     */
    public function authorize(): bool
    {
        // La autorización real se maneja en los middlewares de las rutas o Policies,
        // por lo que aquí retornamos true de forma predeterminada.
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'requirement_id' => ['required', 'uuid'],
            'identifier'     => [
                'required',
                'string',
                'min:4',
                'max:20',
                'regex:/^[a-zA-Z0-9_-]+$/' // Letras, números, guiones y guiones bajos
            ],
            'force'          => ['nullable', 'boolean']
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas de validación.
     */
    public function messages(): array
    {
        return [
            'requirement_id.required' => 'El identificador del requerimiento es obligatorio.',
            'requirement_id.uuid'     => 'El formato del requerimiento no es válido.',
            
            'identifier.required'     => 'El identificador del usuario de prueba es obligatorio.',
            'identifier.min'          => 'El identificador debe tener al menos 4 caracteres.',
            'identifier.max'          => 'El identificador no puede exceder los 20 caracteres.',
            'identifier.regex'        => 'El identificador solo puede contener letras, números, guiones (-) y guiones bajos (_).'
        ];
    }
}