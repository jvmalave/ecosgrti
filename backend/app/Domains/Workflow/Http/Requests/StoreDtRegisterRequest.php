<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\DtRegister;

class StoreDtRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'min:5',
                'max:255',
                Rule::unique(DtRegister::class, 'title')->where(function ($query) {
                    return $query->where('role_id', $this->route('role_id'));
                })
            ],
            'date' => 'required|date',
            'description' => 'required|string',
        ];
    }

 

    public function messages(): array
    {
        return [
            'title.unique' => 'Ya existe un registro con el mismo nombre.',
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede exceder los 255 caracteres.',
            'title.min' => 'El título debe tener al menos 5 caracteres.',
            'date.date' => 'La fecha no es válida.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
        ];
    }
}