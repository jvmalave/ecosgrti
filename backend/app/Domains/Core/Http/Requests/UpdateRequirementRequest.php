<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        // Capturamos el ID de la URL para ignorarlo en la regla unique
        $requirementId = $this->route('id');

        return [
            // Ignoramos el ID actual en la validación unique
            'rrti' => "sometimes|required|string|unique:pgsql.core.requirements,rrti,{$requirementId}",
            'requirement_type' => 'sometimes|required|string',
            'management_type' => 'sometimes|required|string',
            'creation_date' => 'sometimes|required|date',
            'description' => 'sometimes|required|string|min:10',
            
            // Relaciones: Validamos que el ID exista en el esquema correspondiente
            'persona_id' => 'sometimes|required|uuid|exists:pgsql.security.persons,id',
            
            
            'cspe_consultants' => 'sometimes|required|array',
            'cspe_consultants.*' => 'required|uuid|exists:pgsql.security.cspe_consultants,id',
            
            // Archivos: Son 'nullable' porque en una edición el usuario puede no querer cambiarlos.
            // Pero si los envía, deben cumplir con el peso y formato.
            'it_request_doc' => 'nullable|file|mimes:pdf|max:5120',
            'needs_spreadsheet' => 'nullable|file|mimes:pdf,xlsx,xls|max:5120',
        ];
    }
}