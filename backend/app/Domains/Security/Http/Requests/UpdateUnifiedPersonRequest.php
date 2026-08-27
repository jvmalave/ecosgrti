<?php

namespace App\Domains\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Security\Models\Person;
use App\Domains\Security\Models\User;
use App\Domains\Security\Models\RequestingUnit;

class UpdateUnifiedPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    protected function prepareForValidation()
    {
        // Normalizamos los toggles para asegurar que sean booleanos reales (evita nulos o vacíos en false)
        $this->merge([
            'is_functional'     => filter_var($this->input('is_functional'), FILTER_VALIDATE_BOOLEAN),
            'is_cspe'           => filter_var($this->input('is_cspe'), FILTER_VALIDATE_BOOLEAN),
            'has_system_access' => filter_var($this->input('has_system_access'), FILTER_VALIDATE_BOOLEAN),
        ]);

        // Forzamos el acceso al sistema si es consultor CSPE por regla de negocio
        if ($this->input('is_cspe') === true) {
            $this->merge([
                'has_system_access' => true,
            ]);
        }
    }

    public function rules(): array
    {
        // Capturamos el ID de la ruta para excluirlo de las validaciones de unicidad
        $personId = $this->route('id');

        return [
            // 1. Datos Base de Identidad
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes','required', 'string', 'max:255'],
            'email'      => [
                'sometimes',
                'required', 
                'email', 
                'max:255', 
                Rule::unique(Person::class, 'email')->ignore($personId),
            ],
            'phone'      => ['nullable', 'string', 'max:255'],

            // 2. Interruptores Reactivos
            'is_functional'     => ['sometimes','required', 'boolean'],
            'is_cspe'           => ['sometimes','required', 'boolean'],
            'has_system_access' => ['sometimes','required', 'boolean'], 

            // 3. Reglas Condicionales: Consultor Funcional
            'requesting_unit_id' => [
                'nullable',
                'exclude_if:is_functional,false',
                'required_if:is_functional,true',
                'uuid',
                Rule::exists(RequestingUnit::class, 'id')
            ],

            // 4. Reglas Condicionales: Acceso al Sistema
            'name' => [ 
                'nullable',
                'exclude_if:has_system_access,false',
                'required_if:has_system_access,true',
                'string',
                'max:255',
                // El ID del User es el mismo person_id, lo ignoramos para permitir actualizar su propio perfil
                Rule::unique(User::class, 'name')->ignore($personId) 
            ],
            // La contraseña es opcional en la actualización; solo se valida si el usuario decide escribir una nueva
            'password' => [
                'exclude_if:has_system_access,false',
                'nullable',
                'string',
                'min:8'
            ],
            'roles' => [
                'nullable',
                'exclude_if:has_system_access,false',
                'required_if:has_system_access,true',
                'array'
            ],
            'roles.*' => [ 
                'string',
                Rule::in(['Admin', 'admin', 'Coord', 'ConsCSPE', 'Gerente', 'Viewer'])
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'                   => 'Este correo ya se encuentra registrado.',
            'name.unique'                    => 'El identificador de acceso (nombre de usuario) ya está en uso.',
            'password.min'                   => 'La contraseña debe contener al menos 8 caracteres.',
            'requesting_unit_id.required_if' => 'Debe seleccionar una Unidad Solicitante para el perfil Funcional.',
            'requesting_unit_id.exists'      => 'La Unidad Solicitante seleccionada no es válida.',
            'roles.required_if'              => 'Debe asignar al menos un rol de sistema a la cuenta.',
            'roles.*.in'                     => 'Uno de los roles seleccionados no es válido dentro del ecosistema.',
        ];
    }
}