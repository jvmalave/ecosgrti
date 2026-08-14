<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\CorRegister;

class UpdateCorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role_id');
        $regId = $this->route('reg_id');

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                // Excluye el ID actual para permitir guardar sin alterar el título
                Rule::unique(CorRegister::class, 'title')->ignore($regId)->where('role_id', $roleId)
            ],
            'date' => 'required|date|before_or_equal:today',
            'description' => 'required|string|min:10|max:500'
        ];
    }
}