<?php

namespace App\Domains\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permitimos que cualquier usuario intente iniciar sesión;
    }

    
    public function rules(): array
    {
        return [
            // 🟢 Cambiamos 'email' por 'username' y quitamos la validación de formato email
            'username' => 'required|string',
            'password' => 'required|string|min:6', // RN-Cifrado y Validación
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            // 🟢 Ajustamos los mensajes para reflejar la nueva política de seguridad
            'username.required' => 'El nombre de usuario corporativo es obligatorio para iniciar sesión.',
            'username.string'   => 'El formato del nombre de usuario no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos :min caracteres.',
        ];
    }
}