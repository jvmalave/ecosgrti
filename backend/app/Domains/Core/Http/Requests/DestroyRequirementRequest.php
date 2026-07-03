<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyRequirementRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      // El ticket generado en el Paso 1
      'deletion_ticket' => ['required', 'string'],

      // RN-Justificación Obligatoria: Mínimo 10 caracteres
      'justification' => ['required', 'string', 'min:10'],
    ];
  }
}
