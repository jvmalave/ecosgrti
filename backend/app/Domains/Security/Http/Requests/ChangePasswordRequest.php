<?php

namespace App\Domains\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',         // Al menos una mayúscula
                'regex:/[a-z]/',         // Al menos una minúscula
                'regex:/[0-9]/',         // Al menos un número
                'regex:/[.,\-_:]/'       // Al menos un caracter especial permitido
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.regex' => 'La nueva contraseña no cumple con los criterios de seguridad requeridos (mayúscula, minúscula, número o símbolo).',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
        ];
    }
}