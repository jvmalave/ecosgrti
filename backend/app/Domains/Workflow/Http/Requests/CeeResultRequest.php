<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CeeResultRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $rules = [
            'evaluations'   => ['required', 'string'], 
        ];

        if ($this->route()->getActionMethod() === 'registerResult') {
            $rules['file'] = ['required', 'file', 'mimes:pdf', 'max:5120'];
        } else {
            $rules['file'] = ['nullable', 'file', 'mimes:pdf', 'max:5120'];
            $rules['special_auth_token'] = ['required', 'string']; 
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'evaluations.required' => 'El campo "evaluations" es obligatorio.',
            'file.required' => 'El campo "file" es obligatorio.',
        ];
    }
}