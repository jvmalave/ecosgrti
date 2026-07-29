<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;



class StoreSystemRequest extends FormRequest
{


  public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'society_id' => [
                'required', 'uuid',
                Rule::exists('pgsql.catalogs.societies', 'id')->where('is_active', true),
            ],
            'name' => [
                'required', 'string', 'max:150',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = \Illuminate\Support\Facades\DB::table('catalogs.systems')
                        ->where('society_id', request()->input('society_id'))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($exists) {
                        $fail('El nombre del sistema ya existe en esta sociedad.');
                    }
                },
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'society_id.required' => 'Debe asociar el sistema a una sociedad válida.',
            'society_id.exists' => 'La sociedad seleccionada no existe o se encuentra inactiva.',
            'name.required' => 'El nombre del sistema es obligatorio.',
            'name.unique' => 'Ya existe un sistema con este nombre en la sociedad seleccionada.',
        ];
    }
}