<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAtfAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización real la hace el Gatekeeper en el controlador
    }

    public function rules(): array
    {
        return [
            'agreement_date' => ['required', 'date', 'before_or_equal:today'],
            'description'    => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'agreement_date.before_or_equal' => 'La fecha de acuerdo no puede ser posterior a la fecha de hoy.',
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
            'description.max' => 'La descripción no puede tener más de 2000 caracteres.',
        ];
    }
}