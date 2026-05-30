<?php

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequirementRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a hacer esta petición.
     */
    public function authorize(): bool
    {
        // Retornamos true porque la seguridad ya está cubierta por tu middleware auth:api
        return true; 
    }

    /**
     * Reglas de validación estrictas.
     */
    public function rules(): array
    {
        return [
            // Agregamos 'pgsql.' para indicar explícitamente la conexión, el esquema y la tabla
            'rrti' => 'required|string|unique:pgsql.core.requirements,rrti',
            'requirement_type' => 'required|string',
            'management_type' => 'required|string',
            'creation_date' => 'required|date',
            'description' => 'required|string|min:10',
            
            // Lo mismo para el esquema de seguridad
            'functional_consultant_id' => 'required|uuid|exists:pgsql.security.functional_consultants,person_id',
            
            'cspe_consultants' => 'required|array',
            'cspe_consultants.*' => 'required|uuid|exists:pgsql.security.cspe_consultants,id',
            
            // Archivos físicos
            'it_request_doc' => 'required|file|mimes:pdf|max:5120',
            'needs_spreadsheet' => 'required|file|mimes:pdf,xlsx,xls|max:5120',
        ];
    }

    /**
     * (Opcional) Mensajes en español para tu Swagger o Frontend si los necesitas.
     */
    public function messages(): array
    {
        return [
            'rrti.unique' => 'El número RRTI ingresado ya se encuentra registrado en el ecosistema.',
            'it_request_doc.mimes' => 'El documento de Solicitud TI debe ser estrictamente un archivo PDF.',
        ];
    }
}