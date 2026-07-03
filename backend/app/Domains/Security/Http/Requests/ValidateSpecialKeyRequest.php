<?php

declare(strict_types=1);

namespace App\Domains\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateSpecialKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la manejará el RoleMiddleware en la ruta
    }

    public function rules(): array
    {
        return [
            // Exigimos que la clave venga en la petición
            'special_key' => ['required', 'string', 'min:6'],
        ];
    }
}