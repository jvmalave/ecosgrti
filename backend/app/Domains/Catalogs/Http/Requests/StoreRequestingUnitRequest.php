<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;



class StoreRequestingUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

  public function rules(): array
    {
        return [
            'system_id' => [
                'required', 'uuid',
                Rule::exists('pgsql.catalogs.systems', 'id')->where('is_active', true),
            ],
            'name' => [
                'required', 'string', 'max:150',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $exists = \Illuminate\Support\Facades\DB::table('catalogs.requesting_units')
                        ->where('system_id', request()->input('system_id'))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($exists) {
                        $fail('El nombre de la unidad ya se encuentra registrado en el sistema seleccionado.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'system_id.required' => 'Debe vincular la unidad a un sistema operativo.',
            'system_id.exists' => 'El sistema seleccionado no existe o se encuentra inactivo.',
            'name.required' => 'El nombre de la unidad solicitante es obligatorio.',
            'name.unique' => 'Ya existe una unidad solicitante con ese nombre registrada bajo el sistema seleccionado.',
        ];
    }
}