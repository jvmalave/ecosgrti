<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\PiRegister;

class StorePiRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role_id'); // Capturamos el parámetro de la ruta

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                // RN-PI-33: Unicidad del título limitada estrictamente al rol actual
                Rule::unique(PiRegister::class, 'title')
                    ->where('pi_role_id', $roleId)
                    ->whereNull('deleted_at')
            ],
            // RN-PI-35: La fecha no puede ser del futuro
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string']
        ];
    }

    public function messages(): array
    {
        return [
            'title.unique' => 'Ya existe un registro con este título en la bitácora de este rol.',
            'date.before_or_equal' => 'La fecha de la prueba no puede ser posterior al día de hoy.'
        ];
    }

    // RN-PI-40: Saneamiento Defensivo (XSS)
    protected function prepareForValidation()
    {
        if ($this->has('description')) {
            // Mitigación básica XSS (Si tienes HTMLPurifier instalado, úsalo aquí)
            $this->merge([
                'description' => strip_tags($this->description, '<b><i><strong><em><u><ul><ol><li><p><br>')
            ]);
        }
    }
}