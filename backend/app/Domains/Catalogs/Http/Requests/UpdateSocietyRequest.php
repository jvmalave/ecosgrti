<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateSocietyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Obtenemos el UUID de la sociedad desde el parámetro de la ruta
        // Asumiendo que la ruta se llama 'societies' (ej. api/societies/{society})
        $societyId = $this->route('id'); 

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($societyId) {
                    $exists = DB::table('catalogs.societies')
                        ->where('id', '!=', $societyId) // Exclusión del registro actual
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();

                    if ($exists) $fail('El nombre de la sociedad ya existe.');
                },
            ],
            'acronym' => [
                'sometimes', 'required', 'string', 'max:20',
                function (string $attribute, mixed $value, \Closure $fail) use ($societyId) {
                    $exists = DB::table('catalogs.societies')
                        ->where('id', '!=', $societyId) // Exclusión del registro actual
                        ->whereRaw('UPPER(acronym) = ?', [mb_strtoupper($value)])
                        ->exists();

                    if ($exists) $fail('El acrónimo ya se encuentra asignado a otra sociedad.');
                },
            ],
        ];
    }
}