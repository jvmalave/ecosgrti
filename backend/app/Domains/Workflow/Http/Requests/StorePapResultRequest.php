<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Domains\Workflow\Models\PapRole;
use Illuminate\Validation\Rule;
class StorePapResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'evaluations' => ['required', 'array', 'min:1'],
            'evaluations.*.id' => [
                'required', 
                'uuid', 
                Rule::exists(PapRole::class, 'id')
            ],
            'evaluations.*.is_approved' => ['required', 'boolean'],
            'evaluations.*.rejection_reason' => ['nullable', 'string', 'max:255']
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El acta de despliegue es obligatoria.',
            'file.mimes' => 'El acta de despliegue debe ser un archivo PDF válido.',
            'file.max' => 'El documento no debe exceder los 5MB permitidos.',
            'evaluations.required' => 'Debe evaluar al menos un rol de la orden.'
        ];
    }
}