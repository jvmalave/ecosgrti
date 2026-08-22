<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePiApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Validación estricta para el documento PDF (Máx 5MB = 5120 KB)
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El acta de aprobación en formato PDF es obligatoria.',
            'file.file' => 'El elemento cargado no es un archivo válido.',
            'file.mimes' => 'El documento de aprobación debe ser obligatoriamente un PDF.',
            'file.max' => 'El tamaño del documento no debe superar los 5 MB.',
        ];
    }
}