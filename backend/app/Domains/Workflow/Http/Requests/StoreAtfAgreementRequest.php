<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Auth\Access\AuthorizationException;
use App\Domains\Core\Models\Requirement;

class StoreAtfAgreementRequest extends FormRequest
{
    // Declaramos una propiedad para almacenar el requerimiento resuelto
    private Requirement $resolvedRequirement;

    public function authorize(): bool
    {
        $routeParams = $this->route()->parameters();
        $param = !empty($routeParams) ? reset($routeParams) : $this->segment(4); 

        if ($param instanceof Requirement) {
            $requirement = $param;
        } else {
            $requirement = Requirement::where('id', $param)->first();
        }

        if (!$requirement) {
            $idBuscado = is_string($param) ? $param : 'Nulo/Desconocido';
            throw new AuthorizationException("Error de Sistema: Requerimiento no encontrado en la Base de Datos. ID capturado: {$idBuscado}");
        }

        // Guardamos la instancia en la clase para usarla en rules() y messages()
        $this->resolvedRequirement = $requirement;

        $allowedStatuses = ['ES-R', 'ATF-I'];

        if (!in_array($requirement->status, $allowedStatuses)) {
            throw new AuthorizationException(
                "Acceso Denegado: El requerimiento (Estado: {$requirement->status}) se encuentra sellado y es de solo lectura."
            );
        }

        return true; 
    }

    public function rules(): array
    {
        // Extraemos la fecha de creación en formato YYYY-MM-DD
        $creationDate = $this->resolvedRequirement->created_at->format('Y-m-d');

        return [
            'agreement_date' => [
                'required', 
                'date', 
                'before_or_equal:today',
                'after_or_equal:' . $creationDate // Bloqueo de fechas previas
            ],
            'description'    => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        // Formateamos la fecha para un mensaje amigable al usuario
        $creationDateUser = $this->resolvedRequirement->created_at->format('d/m/Y');

        return [
            'agreement_date.before_or_equal' => 'La fecha de acuerdo no puede ser posterior a la fecha de hoy.',
            'agreement_date.after_or_equal' => "La fecha de acuerdo no puede ser anterior a la creación del requerimiento ({$creationDateUser}).",
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
            'description.max' => 'La descripción no puede tener más de 2000 caracteres.',
        ];
    }
}