<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\CoeActivity;

class StoreCoeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deliverableId = $this->route('deliverable_id');

        return [
            'title' => [
                'required',
                'string',
                'min:5',
                'max:255',
                // Unicidad circunscrita exclusivamente al coe_deliverable_id actual
                Rule::unique(CoeActivity::class, 'title')->where('coe_deliverable_id', $deliverableId)
            ],
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|min:10'
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título de la actividad es obligatorio.',
            'title.unique' => 'Ya existe una actividad con este título en la bitácora de este entregable.',
            'title.min' => 'El título debe contener al menos 5 caracteres.',
            'date.required' => 'La fecha de la evidencia es obligatoria.',
            'date.before_or_equal' => 'La fecha no puede ser mayor al día de hoy.',
            'description.required' => 'La descripción técnica es obligatoria.',
            'description.min' => 'La descripción debe ser detallada (mínimo 10 caracteres).',
        ];
    }
}