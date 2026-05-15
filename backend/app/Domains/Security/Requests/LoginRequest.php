<?php

namespace App\Domains\Security\Requests;


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
        'email'    => 'required|email',
        'password' => 'required|string|min:6', // RN-Cifrado y Validación
    ];
    }

    /**
 * Mensajes de error personalizados.
 */
public function messages(): array
{
    return [
        'email.required'    => 'El correo electrónico es obligatorio para iniciar sesión.',
        'email.email'       => 'El formato del correo electrónico no es válido.',
        'password.required' => 'La contraseña es obligatoria.',
        'password.min'      => 'La contraseña debe tener al menos :min caracteres.',
    ];
}
}
