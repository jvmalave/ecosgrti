<?php

namespace App\Domains\Catalogs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishMatrixVersionRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        // RN-Seguridad: Se retorna true ya que el bloqueo perimetral 
        // se maneja a través de los Gatekeepers y Middleware RBAC.
        return true;
    }

    /**
     * Reglas de validación base para los atributos del payload.
     */
    public function rules(): array
    {
        return [
            'management_type' => ['required', 'string', 'in:ROLES,ENTREGABLES,MIXTO'],
            'milestones' => ['required', 'array', 'min:1'],
            
            // RN-Aislamiento Total: Declaramos explícitamente la conexión (pgsql)
            // para evitar que Laravel confunda el esquema con una base de datos externa.
            'milestones.*.milestone_id' => [
                'required', 
                'uuid', 
                'exists:pgsql.catalogs.milestones,id'
            ],
            
            // FS-03: Peso mínimo por hito de 0.01%
            'milestones.*.weight' => ['required', 'numeric', 'min:0.01'], 
        ];
    }

    /**
     * RN-Hard Gate Server-Side (FS-01): Validación Matemática Estricta al 100.00%.
     * Este hook se ejecuta después de que las reglas base hayan pasado exitosamente.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $milestones = collect($this->input('milestones', []));
                
                // RN-Precisión Decimal: Sumamos y formateamos estrictamente a 2 decimales
                $totalSum = $milestones->pluck('weight')->sum();
                $formattedSum = number_format($totalSum, 2, '.', '');

                if ($formattedSum !== '100.00') {
                    $validator->errors()->add(
                        'milestones', 
                        "La suma de los pesos debe ser estrictamente igual al 100.00%. Suma actual: {$formattedSum}%"
                    );
                }
            }
        ];
    }
}