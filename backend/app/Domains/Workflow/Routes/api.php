<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Workflow\Http\Controllers\ATFAgreementController;
use App\Domains\Workflow\Http\Controllers\ProgressDashboardController;
use App\Domains\Workflow\Http\Controllers\RequirementRoleController;
use App\Domains\Workflow\Http\Controllers\DeliverableController;
use App\Domains\Workflow\Http\Controllers\ATFClosureController;

// Aplicamos el middleware a todo el grupo de workflow para centralizar la seguridad
Route::middleware(['auth:api'])->prefix('workflow')->group(function () {
    
    // US25: Registrar Acuerdo ATF
    Route::post('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'store']);
    
    // Mostrar Dashboard de Progreso
    Route::get('/requirements/{requirementId}/progress-dashboard', [ProgressDashboardController::class, 'show']);

    // US26: Gestión de Componentes Técnicos (Roles)
    Route::post('/requirements/{requirementId}/roles', [RequirementRoleController::class, 'store']);
    Route::put('/components/roles/{roleId}', [RequirementRoleController::class, 'update']);

    // US27: Gestión de Componentes Técnicos (Entregables)
    Route::post('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'store']);
    Route::put('/components/deliverables/{deliverableId}', [DeliverableController::class, 'update']);

    // US28: Gestión de Cierre de Fase (Hard Gate)
    Route::get('/requirements/{requirementId}/closure-readiness', [ATFClosureController::class, 'checkReadiness']);
    Route::post('/requirements/{requirementId}/close-atf', [ATFClosureController::class, 'closePhase']);
});