<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PhaseRequirementNotMetException extends Exception
{
    /**
     * Intercepta la excepción y retorna automáticamente un 403 Forbidden a Angular.
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'error' => 'Acceso Denegado: Violación de Regla de Negocio',
            'message' => $this->getMessage(),
        ], 403);
    }
}