<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Domains\Workflow\Models\PapOrder;
use App\Domains\Workflow\Models\PapRole;
use Illuminate\Validation\Rule;

class StorePapOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'order_number' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique(PapOrder::class, 'order_number')
            ],
            'date' => [
                'required', 
                'date'
            ],
            'file' => [
                'required', 
                'file', 
                'mimes:pdf', 
                'max:5120'
            ],
            'role_ids' => [
                'required', 
                'array', 
                'min:1'
            ],
            'role_ids.*' => [
                'required', 
                'uuid', 
                Rule::exists(PapRole::class, 'id')
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'order_number.required' => 'El número de Orden de Transporte es obligatorio.',
            'order_number.unique' => 'El número de Orden de Transporte ya se encuentra registrado en el sistema.',
            'date.required' => 'La fecha de la orden es obligatoria.',
            'date.date' => 'La fecha ingresada no tiene un formato válido.',
            'file.required' => 'Debe adjuntar el documento de soporte.',
            'file.mimes' => 'El documento de soporte debe ser un archivo PDF válido.',
            'file.max' => 'El documento no debe exceder los 5MB permitidos.',
            'role_ids.required' => 'Debe seleccionar al menos un componente para esta orden.',
            'role_ids.*.exists' => 'Uno de los componentes seleccionados no es válido o no pertenece a esta fase.'
        ];
    }
}