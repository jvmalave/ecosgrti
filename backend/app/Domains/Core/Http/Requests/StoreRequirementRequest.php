<?php

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule; 
use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\FunctionalConsultant;
use App\Domains\Security\Models\CspeConsultant;

class StoreRequirementRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        // Como la ruta ya estará protegida por el middleware de autenticación (y rol),
        // devolvemos true para permitir que pase a la validación.
        return true;
    }

    /**
     * Las reglas de validación que se aplicarán a la petición.
     */
    public function rules(): array
    {
        //dd($this->all());
        return [
            // Usamos Rule::unique con la clase del Modelo
            'rrti' => ['required', 'string', 'max:255', Rule::unique(Requirement::class, 'rrti')],
            
            'requirement_type' => ['required', 'string', 'max:100'],
            'creation_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'management_type' => ['required', 'string', 'max:100'],
            
            'needs_spreadsheet' => ['required', 'file', 'mimes:pdf', 'max:3072'],
            'it_request_doc' => ['required', 'file', 'mimes:pdf', 'max:3072'],

            // Usamos Rule::exists con la clase del Modelo
            'functional_consultant_id' => [
                'required', 
                'uuid', 
                Rule::exists(FunctionalConsultant::class, 'id')
            ],
            
            'cspe_consultants' => ['required', 'array', 'min:1'],
            'cspe_consultants.*' => [
                'required', 
                'uuid', 
                Rule::exists(CspeConsultant::class, 'id')
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rrti.unique' => 'El número de requerimiento (RRTI) ingresado ya existe en el sistema.',
            'cspe_consultants.min' => 'Debe asignar al menos un consultor CSPE al requerimiento.',
            'functional_consultant_id.exists' => 'El consultor funcional seleccionado no es válido.',
            
            // Puedes agregar más mensajes personalizados si lo deseas
        ];
    }
}