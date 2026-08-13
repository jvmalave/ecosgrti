<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\CoeActivity;

class UpdateCoeActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deliverableId = $this->route('deliverable_id');
        $activityId = $this->route('activity_id'); 

        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'min:5',
                'max:255',
                // Unicidad circunscrita ignorando el ID actual
                Rule::unique(CoeActivity::class, 'title')
                    ->where('coe_deliverable_id', $deliverableId)
                    ->ignore($activityId)
            ],
            'date' => 'sometimes|required|date|before_or_equal:today',
            'description' => 'sometimes|required|string|min:10'
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título de la actividad es obligatorio.',
            'title.unique' => 'Ya existe otra actividad con este título en la bitácora.',
            'title.min' => 'El título debe contener al menos 5 caracteres.',
            'date.required' => 'La fecha de la evidencia es obligatoria.',
            'date.before_or_equal' => 'La fecha no puede registrar un evento futuro.',
            'description.required' => 'La descripción técnica es obligatoria.',
            'description.min' => 'La descripción debe proporcionar más detalle (mínimo 10 caracteres).',
        ];
    }
}