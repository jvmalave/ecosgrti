<?php

namespace App\Domains\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ✅ Importamos los modelos para blindar el mapeo de esquemas
use App\Domains\Security\Models\Person;
use App\Domains\Security\Models\User;
use App\Domains\Security\Models\RequestingUnit; // <-- Ajusta la ruta si este modelo está en el dominio Catalog

class StoreUnifiedPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            // ==========================================
            // 1. Datos Base de Identidad
            // ==========================================
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => [
                'required', 
                'email', 
                'max:255', 
                Rule::unique(Person::class, 'email'),
            ],
            'phone'      => ['nullable', 'string', 'max:255'],

            // ==========================================
            // 2. Interruptores Reactivos (Toggles UI)
            // ==========================================
            'is_functional'     => ['required', 'boolean'],
            'is_cspe'           => ['required', 'boolean'],
            'has_system_access' => ['required', 'boolean'], 

            // ==========================================
            // 3. Reglas Condicionales: Consultor Funcional
            // ==========================================
            'requesting_unit_id' => [
                'exclude_if:is_functional,false',
                'required_if:is_functional,true',
                'uuid',
                Rule::exists(RequestingUnit::class, 'id')
            ],

            // ==========================================
            // 4. Reglas Condicionales: Acceso al Sistema
            // ==========================================
            'name' => [ 
                'exclude_if:has_system_access,false',
                'required_if:has_system_access,true',
                'string',
                'max:255',
                Rule::unique(User::class, 'name') 
            ],
            'password' => [
                'exclude_if:has_system_access,false',
                'required_if:has_system_access,true',
                'string',
                'min:8'
            ],
            'roles' => [
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

    protected function prepareForValidation()
    {
        if ($this->input('is_cspe') === true) {
            $this->merge([
                'has_system_access' => true,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'email.unique'                   => 'Este correo ya se encuentra registrado. FS-01 Fallo de Unicidad.',
            'name.unique'                    => 'El identificador de acceso (nombre) ya está en uso.',
            'password.min'                   => 'La contraseña debe contener al menos 8 caracteres.',
            'requesting_unit_id.required_if' => 'Debe seleccionar una Unidad Solicitante para el perfil Funcional.',
            'requesting_unit_id.exists'      => 'La Unidad Solicitante seleccionada no es válida.',
            'roles.required_if'              => 'Debe asignar al menos un rol de sistema a la cuenta.',
            'roles.*.in'                     => 'Uno de los roles seleccionados no es válido dentro del ecosistema.',
        ];
    }
}