<?php

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Domains\Workflow\Models\PiRegister;

class UpdatePiRegisterRequest extends FormRequest
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
                Rule::unique(PiRegister::class, 'title')
                    ->where('pi_role_id', $roleId)
                    ->ignore($regId)
                    ->whereNull('deleted_at')
            ],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string']
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('description')) {
            $this->merge([
                'description' => strip_tags($this->description, '<b><i><strong><em><u><ul><ol><li><p><br>')
            ]);
        }
    }
}