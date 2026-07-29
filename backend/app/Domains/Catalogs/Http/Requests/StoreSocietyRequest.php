<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;




class StoreSocietyRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        // La autorización se maneja mediante el middleware de RBAC configurado
        return true;
    }

    /**
     * Reglas de validación para registrar una nueva Sociedad.
     */

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:150',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = \Illuminate\Support\Facades\DB::table('catalogs.societies')
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($exists) $fail('El nombre de la sociedad ya existe.');
                },
            ],
            'acronym' => [
                'required', 'string', 'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = \Illuminate\Support\Facades\DB::table('catalogs.societies')
                        ->whereRaw('UPPER(acronym) = ?', [mb_strtoupper($value)])
                        ->exists();

                    if ($exists) $fail('El acrónimo ya se encuentra asignado a otra sociedad.');
                },
            ],
        ];
    }
    /**
     * Mensajes personalizados de error de validación.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la sociedad es obligatorio.',
            'name.unique' => 'Ya existe una sociedad registrada con ese nombre.',
            'acronym.required' => 'El acrónimo es obligatorio.',
            'acronym.unique' => 'El acrónimo ya se encuentra asignado a otra sociedad.',
        ];
    }
}