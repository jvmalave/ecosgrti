<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCerTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'request_date'  => ['sometimes', 'date', 'before_or_equal:today'],
            'file'          => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'role_ids'      => ['required', 'array', 'min:1'],
            'role_ids.*'    => ['uuid']
        ];
    }

    public function messages(): array
    {
        return [
            'role_ids.*.uuid' => 'El rol seleccionado no es valido',
            'role_ids.required' => 'Debe seleccionar al menos un rol',
            'role_ids.min' => 'Debe seleccionar al menos un rol',
            'file.file' => 'El elemento cargado no es un archivo valido.',
            'file.mimes' => 'El documento debe ser obligatoriamente un PDF.',
            'file.max' => 'El tamaño del documento no debe superar los 5 MB.',
            'request_date.before_or_equal' => 'La fecha no puede ser mayor al dia de hoy.',
        ];
    }
}