<?php

namespace App\Domains\Security\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnifiedPersonResource extends JsonResource
{
    /**
     * Transforma el recurso en un arreglo estructurado para el Frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            
            // Banderas booleanas computadas dinámicamente para facilitar la reactividad de Signals en Angular
            'is_functional' => $this->whenLoaded('functionalConsultant', fn() => $this->functionalConsultant !== null, false),
            'is_cspe' => $this->whenLoaded('cspeConsultant', fn() => $this->cspeConsultant !== null, false),
            'has_system_access' => $this->whenLoaded('user', fn() => $this->user !== null, false),

            // Perfiles anidados (Solo se incluyen de forma segura si la relación fue cargada previamente mediante Eager Loading)
            'profiles' => [
                'user' => $this->whenLoaded('user', function () {
                    return [
                        'username' => $this->user->name,
                        'roles' => $this->user->roles,
                    ];
                }),
                'functional' => $this->whenLoaded('functionalConsultant', function () {
                    return [
                        'requesting_unit_id' => $this->functionalConsultant->requesting_unit_id,
                        // Si necesitas enviar datos de la unidad o sociedad, puedes anidar más carga aquí
                    ];
                }),
                'cspe' => $this->whenLoaded('cspeConsultant', function () {
                    return [
                        'id' => $this->cspeConsultant->id,
                    ];
                }),
            ],
            
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}