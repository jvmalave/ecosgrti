<?php

namespace App\Domains\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Domains\Workflow\Services\PapRoleService;
use App\Domains\Workflow\Services\PapWorkflowService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PapRoleController extends Controller
{
    public function __construct(
        private PapRoleService $papRoleService
        , private PapWorkflowService $papWorkflowService
    ) {}

    public function initRoles(string $requirementId): JsonResponse
    {
        try {
            $roles = $this->papRoleService->initializePapRoles($requirementId);
            
            return response()->json([
                'requirement_id' => $requirementId,
                'roles' => $roles
            ], 200);
            
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 403);
        }
    }


    public function closePhase(string $requirementId): JsonResponse
    {
        return response()->json($this->papWorkflowService->closeGlobalPhase($requirementId));
    }
}