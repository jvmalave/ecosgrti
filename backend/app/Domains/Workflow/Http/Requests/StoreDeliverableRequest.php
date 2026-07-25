<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\Deliverable;

class StoreDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización de entradas para prevenir ataques XSS (Cross-Site Scripting).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->name ? strip_tags(trim($this->name)) : null,
            'description' => $this->description ? strip_tags(trim($this->description)) : null,
        ]);
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            $requirementId = $this->route('requirementId');
            $deliverableId = null;
        } else {
            // En PUT, extraemos el requirement_id directamente de la base de datos
            $deliverableId = $this->route('deliverableId');
            $deliverable = Deliverable::find($deliverableId);
            $requirementId = $deliverable ? $deliverable->requirement_id : null;
        }

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(Deliverable::class, 'name')
                    ->where('requirement_id', $requirementId)
                    ->whereNull('deleted_at')
                    ->ignore($deliverableId, 'id'),
            ],
            'description' => 'required|string|min:10|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del entregable es obligatorio.',
            'name.unique' => 'Este entregable ya está asignado a este requerimiento.',
            'description.max' => 'La descripción no puede exceder los 500 caracteres.',
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
        ];
    }
}