<?php

namespace App\Domains\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Core\Services\RequirementDashboardService;

class RequirementDashboardController extends Controller
{
    protected RequirementDashboardService $dashboardService;

    public function __construct(RequirementDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Endpoint: GET /requirements?status=active&limit=10&offset=0
     */
    public function index(Request $request)
    {
        // Captura de parámetros con valores por defecto
        $status = $request->query('status', 'active');
        $limit = (int) $request->query('limit', 10);
        $offset = (int) $request->query('offset', 0);

        // Validaciones básicas de seguridad para paginación
        if ($limit > 50) $limit = 50; 
        if ($offset < 0) $offset = 0;

        $data = $this->dashboardService->getRequirements($status, $limit, $offset);

        return response()->json($data, 200);
    }
}