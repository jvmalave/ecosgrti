<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Workflow\Http\Controllers\ATFAgreementController;
use App\Domains\Workflow\Http\Controllers\ProgressDashboardController;
use App\Domains\Workflow\Http\Controllers\RequirementRoleController;
use App\Domains\Workflow\Http\Controllers\DeliverableController;
use App\Domains\Workflow\Http\Controllers\ATFClosureController;
use App\Domains\Workflow\Http\Controllers\UpdateManagementTypeController;

// Aplicamos el middleware a todo el grupo de workflow para centralizar la seguridad
Route::middleware(['auth:api'])->prefix('workflow')->group(function () {


  // ====== US25: Gestión de Acuerdos ATF ======
    // Registrar Acuerdo ATF
    Route::post('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'store']);

    // Actualizar Acuerdo ATF
    Route::put('/requirements/{requirementId}/atf-agreements/{agreementId}', [ATFAgreementController::class, 'update']);

    // Listar Acuerdos ATF de un Requerimiento
    Route::get('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'index']);
    
    // Eliminar Acuerdo ATF
    Route::delete('/requirements/{requirementId}/atf-agreements/{agreementId}', [ATFAgreementController::class, 'destroy']);


    //===== US26: Gestión de Componente Roles) ======
    // Registrar Rol 
    Route::post('/requirements/{requirementId}/roles', [RequirementRoleController::class, 'store']);

    // Listar Roles de un Requerimiento
    Route::get('/requirements/{requirementId}/components-data', [RequirementRoleController::class, 'index']);
    
    // Actualizar Rol
    Route::put('/roles/{roleId}', [RequirementRoleController::class, 'update']);

    // Eliminar Rol
    Route::delete('/roles/{roleId}', [RequirementRoleController::class, 'destroy']);


    // ===== US27: Gestión de Componente Entregables  =====
    // Registrar Entregable
    Route::post('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'store']);

    // Listar Entregables de un Requerimiento
    Route::get('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'index']);

    // Actualizar Entregable
    Route::put('/deliverables/{deliverableId}', [DeliverableController::class, 'update']);

    // Eliminar Entregable
    Route::delete('/deliverables/{deliverableId}', [DeliverableController::class, 'destroy']);


    // ====US28: Gestión de Cierre de Fase (Hard Gate)====
    
    // Verificación de Quórum
    Route::get('/requirements/{id}/closure-readiness', [ATFClosureController::class, 'checkReadiness']);
    
    // Confirmación y Cierre Atómico
    Route::post('/requirements/{id}/close-atf', [ATFClosureController::class, 'closePhase']);
    

    
    // =====  Otras =====
    // Mostrar Dashboard de Progreso
    Route::get('/requirements/{requirementId}/progress-dashboard', [ProgressDashboardController::class, 'show']);

    // Actualizar Tipo de Gestión
    Route::patch('/requirements/{requirementId}/management-type', UpdateManagementTypeController::class);
});



