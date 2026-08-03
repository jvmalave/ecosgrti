<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateRequestingUnitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // $unitId = $this->route('requesting_unit') ?? $this->route('unit');
        $unitId = $this->route('id');
        
        // Garantizamos tener el system_id actual para validar colisiones en el mismo nivel
        $systemId = $this->input('system_id') ?? DB::table('catalogs.requesting_units')->where('id', $unitId)->value('system_id');

        return [
            'system_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('pgsql.catalogs.systems', 'id')->where('is_active', true),
            ],
            'name' => [
                'sometimes', 'required', 'string', 'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($unitId, $systemId) {
                    $exists = DB::table('catalogs.requesting_units')
                        ->where('id', '!=', $unitId) // Exclusión del registro actual
                        ->where('system_id', $systemId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($exists) {
                        $fail('El nombre de la unidad ya se encuentra registrado en el sistema seleccionado.');
                    }
                },
            ],
        ];
    }
}