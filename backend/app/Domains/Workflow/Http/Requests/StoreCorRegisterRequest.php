<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\CorRegister;

class StoreCorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se maneja por middlewares/policies de fase
    }

    public function rules(): array
    {
        $roleId = $this->route('role_id');

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                // Unicidad circunscrita exclusivamente al role_id actual
                Rule::unique(CorRegister::class, 'title')->where('role_id', $roleId)
            ],
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|min:10|max:500'
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.unique' => 'El título ya se encuentra registrado para este rol.',
            'date.before_or_equal' => 'La fecha no puede ser superior al día de hoy.'
        ];
    }
}