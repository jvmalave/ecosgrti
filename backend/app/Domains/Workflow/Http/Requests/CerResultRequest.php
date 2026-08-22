<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerResultRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rules = [
            'evaluations'   => ['required', 'string'], // Angular envía un JSON stringificado
        ];

        // Si es POST (creación), el PDF es obligatorio. Si es PUT/PATCH/POST-update, es opcional.
        if ($this->route()->getActionMethod() === 'registerResult') {
            $rules['file'] = ['required', 'file', 'mimes:pdf', 'max:5120'];
        } else {
            $rules['file'] = ['nullable', 'file', 'mimes:pdf', 'max:5120'];
            $rules['special_auth_token'] = ['required', 'string']; // Obligatorio para actualizaciones
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'evaluations.required' => 'La evaluación es obligatoria.',
        ];
    }
}