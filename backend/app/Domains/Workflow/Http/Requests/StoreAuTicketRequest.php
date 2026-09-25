<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\AuTicket;
use App\Domains\Workflow\Models\AuRole;

class StoreAuTicketRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        
        return true; 
    }

    
    public function rules(): array
    {
        return [
            'ticket_number' => [
                'required', 
                'string', 
                'max:50',
                Rule::unique(AuTicket::class, 'ticket_number')
            ],
            'request_date' => [
                'required', 
                'date', 
                'before_or_equal:today'
            ],
            
            'file' => [
                'required', 
                'file', 
                'mimes:pdf', 
                'max:5070' // Max 5MB
            ],
            'role_ids' => [
                'required', 
                'array', 
                'min:1'
            ],
            'role_ids.*' => [
                'required', 
                'uuid', 
                Rule::exists(AuRole::class, 'id')->where(function ($query) {
                    $query->where('status', 'PENDING_AU');
                })
            ],
            'planillas' => [
                'required', 
                'array'
            ],
            'planillas.*' => [
                'required', 
                'file', 
                'mimes:pdf', 
                'max:5120' // Max 5MB por planilla
            ],
        ];
    }

    /**
     * Mensajes personalizados para el frontend en Angular.
     */
    public function messages(): array
    {
        return [
            'ticket_number.required' => 'El número de ticket es obligatorio.',
            'ticket_number.unique'   => 'El número de ticket ya se encuentra registrado en el sistema.',
            'request_date.required'  => 'La fecha de la solicitud es obligatoria.',
            'request_date.before_or_equal' => 'La fecha de solicitud no puede ser una fecha futura.',
            'file.required'          => 'Debe adjuntar el archivo PDF general de la solicitud.',
            'file.mimes'             => 'El documento base de la solicitud debe ser un documento PDF válido.',
            'file.max'               => 'El dicumento base de la solicitud debe pesar menos de 5MB.',
            'role_ids.required'      => 'Debe seleccionar al menos un rol para este trámite.',
            'role_ids.*.exists'      => 'Uno de los roles seleccionados no es válido o ya se encuentra en trámite.',
            'planillas.required'     => 'Debe adjuntar las planillas individuales de los roles seleccionados.',
            'planillas.*.mimes'      => 'Todas las planillas individuales deben ser documentos PDF válidos.',
            'planillas.*.max'        => 'Todas las planillas individuales deben pesar menos de 5MB.',
        ];
    }
}