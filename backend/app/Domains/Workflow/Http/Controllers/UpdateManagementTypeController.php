<?php

namespace App\Domains\Workflow\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Domains\Workflow\Services\ATFService; // O el servicio donde pongas la lógica

class UpdateManagementTypeController extends Controller
{
    public function __construct(
        private ATFService $atfService // Inyectamos tu servicio
    ) {}

    /**
     * Actualiza el tipo de gestión y recalcula el progreso (CU-017.5)
     */
    public function __invoke(Request $request, string $requirementId)
    {
        // 1. Validación estricta
        $validated = $request->validate([
            'tipo_gestion' => 'required|string|in:ROLES,ENTREGABLES,MIXTO'
        ]);

        // 2. Delegamos la transacción al servicio
        $result = $this->atfService->updateManagementType($requirementId, $validated['tipo_gestion']);

        // 3. Respuesta JSON reactiva para Angular
        return response()->json([
            'message'         => 'Tipo de gestión actualizado correctamente.',
            'tipo_gestion'    => $result['tipo_gestion'],
            'progreso_global' => $result['progreso_global']
        ], 200);
    }
}