<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCeeTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // 🟢 RN-CEE: Aquí NO hay 'ticket_number' porque lo genera el sistema
            'request_date'    => ['required', 'date', 'before_or_equal:today'],
            'file'            => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'deliverable_ids' => ['required', 'array', 'min:1'],
            'deliverable_ids.*' => ['uuid']
        ];
    }

    public function messages(): array
    {
        return [
            'deliverable_ids.*.uuid' => 'El ID del entregable debe ser un UUID v4.',
            'file.mimes' => 'El archivo debe ser un PDF.',
            'file.max' => 'El archivo debe pesar menos de 5MB.',
            'request_date.before_or_equal' => 'La fecha no puede ser mayor al dia de hoy.',
        ];
    }
}