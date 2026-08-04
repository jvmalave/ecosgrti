<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\DtRegister;

class UpdateDtRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Obtenemos el registro actual para extraer su role_id y aplicar la regla de unicidad correctamente
        $registerId = $this->route('reg_id');
        $register = DtRegister::findOrFail($registerId);

        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'min:5',
                'max:255',

                // RN-03: Unicidad excluyendo el ID actual, validando contra el mismo rol
                Rule::unique(DtRegister::class, 'title')
                    ->where(function ($query) use ($register) {
                        return $query->where('role_id', $register->role_id);
                    })
                    ->ignore($registerId)
            ],
            'date' => 'sometimes|required|date',
            'description' => 'sometimes|required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.unique' => 'Ya existe un registro con el mismo nombre para este rol.',
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede exceder los 255 caracteres.',
            'date.date' => 'La fecha no es válida.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
        ];
    }
}