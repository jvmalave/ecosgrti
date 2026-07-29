<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateSystemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $systemId = $this->route('id');
        
        // Si no se envía el society_id en el request de actualización, 
        // lo recuperamos de la base de datos para mantener la validación jerárquica.
        $societyId = $this->input('society_id') ?? DB::table('catalogs.systems')->where('id', $systemId)->value('society_id');

        return [
            'society_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('pgsql.catalogs.societies', 'id')->where('is_active', true),
            ],
            'name' => [
                'sometimes', 'required', 'string', 'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($systemId, $societyId) {
                    $exists = DB::table('catalogs.systems')
                        ->where('id', '!=', $systemId) // Exclusión del registro actual
                        ->where('society_id', $societyId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($exists) $fail('El nombre del sistema ya existe en esta sociedad.');
                },
            ],
        ];
    }
}