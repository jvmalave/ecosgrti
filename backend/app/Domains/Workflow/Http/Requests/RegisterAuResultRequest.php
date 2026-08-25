<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\AuRole;

class RegisterAuResultRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // RN-AU-23: Validación de Soporte
            'result_file' => ['required', 'file', 'mimes:pdf', 'max:5120'], 
            'reception_date' => ['required', 'date', 'before_or_equal:today'],
            
            // Evaluación Granular
            'evaluations' => ['required', 'array', 'min:1'],
            'evaluations.*.id' => [
                'required', 
                'uuid', 
                Rule::exists(AuRole::class, 'id')
            ],
            'evaluations.*.is_approved' => ['required', 'boolean'],
            
            
            'evaluations.*.rejection_reason' => [
                'exclude_if:evaluations.*.is_approved,true',
                'required',
                'string',
                'min:10'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // RN-AU-22: Justificación obligatoria >= 10 caracteres si no es aprobado
            'evaluations.*.rejection_reason.required' => 'La justificación es obligatoria.',
            'evaluations.*.rejection_reason.min' => 'La justificación debe contener al menos 10 caracteres.',
            'evaluations.*.rejection_reason.string' => 'La justificación debe ser una cadena de texto.',
            'result_file.required' => 'El archivo es obligatorio.',
            'result_file.file' => 'El archivo debe ser un archivo.',
            'result_file.mimes' => 'El archivo debe ser un archivo PDF.',
            'result_file.max' => 'El archivo debe tener un tamaño máximo de 5MB.',
            'reception_date.required' => 'La fecha de recepción es obligatoria.',
            'reception_date.date' => 'La fecha de recepción debe ser una fecha válida.',
            'reception_date.before_or_equal' => 'La fecha de recepción debe ser anterior o igual a la fecha actual.',

        ];
    }
}