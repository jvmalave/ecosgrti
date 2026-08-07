<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Workflow\Http\Controllers\ATFAgreementController;
use App\Domains\Workflow\Http\Controllers\ProgressDashboardController;
use App\Domains\Workflow\Http\Controllers\RequirementRoleController;
use App\Domains\Workflow\Http\Controllers\DeliverableController;
use App\Domains\Workflow\Http\Controllers\ATFClosureController;
use App\Domains\Workflow\Http\Controllers\UpdateManagementTypeController;

// Controladores US29: Diseño Técnico (DT)
use App\Domains\Workflow\Http\Controllers\DtRoleController;
use App\Domains\Workflow\Http\Controllers\DtRegisterController;

// Aplicamos el middleware a todo el grupo de workflow para centralizar la seguridad
Route::middleware(['auth:api'])->prefix('workflow')->group(function () {

  Route::middleware(['role:Admin,Coord,ConsCSPE'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | GESTUON DE ACUERDOS ATF 
    |--------------------------------------------------------------------------
    */
    // Registrar Acuerdo ATF
    Route::post('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'store']);
    // Actualizar Acuerdo ATF
    Route::put('/requirements/{requirementId}/atf-agreements/{agreementId}', [ATFAgreementController::class, 'update']);
    // Listar Acuerdos ATF de un Requerimiento
    Route::get('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'index']);
    // Eliminar Acuerdo ATF
    Route::delete('/requirements/{requirementId}/atf-agreements/{agreementId}', [ATFAgreementController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | GESTION DE COMPONENTE ROLES
    |--------------------------------------------------------------------------
    */
    
    // Registrar Rol 
    Route::post('/requirements/{requirementId}/roles', [RequirementRoleController::class, 'store']);
    // Listar Roles de un Requerimiento
    Route::get('/requirements/{requirementId}/components-data', [RequirementRoleController::class, 'index']);
    // Actualizar Rol
    Route::put('/roles/{roleId}', [RequirementRoleController::class, 'update']);
    // Eliminar Rol
    Route::delete('/roles/{roleId}', [RequirementRoleController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN DE COMPONENTE ENTREGABLES
    |--------------------------------------------------------------------------
    */
    // Registrar Entregable
    Route::post('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'store']);
    // Listar Entregables de un Requerimiento
    Route::get('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'index']);
    // Actualizar Entregable
    Route::put('/deliverables/{deliverableId}', [DeliverableController::class, 'update']);
    // Eliminar Entregable
    Route::delete('/deliverables/{deliverableId}', [DeliverableController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN DE CIERRE DE FASE (Hard Gate)
    |--------------------------------------------------------------------------
    */
    // Verificación de Quórum
    Route::get('/requirements/{id}/closure-readiness', [ATFClosureController::class, 'checkReadiness']);
    // Confirmación y Cierre Atómico
    Route::post('/requirements/{id}/close-atf', [ATFClosureController::class, 'closePhase']);

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN DE DISEÑO TÉCNICO (DT)
    |--------------------------------------------------------------------------
    */
    // Acceder a Gestión de Diseño Técnico (DT) y sincronizar roles
    Route::get('/requirements/{id}/dt/roles-init', [DtRoleController::class, 'index']);
    
    // Gestionar Ciclo de Vida del Rol en DT (Cerrar/Activar)
    Route::patch('/dt/roles/{role_id}/status', [DtRoleController::class, 'changeStatus']);
    
    // Consultar Lista de Registros por Rol
    Route::get('/dt/roles/{role_id}/registers', [DtRegisterController::class, 'index']);
    
    // Agregar Registro de Diseño Técnico
    Route::post('/requirements/{id}/dt/roles/{role_id}/registers', [DtRegisterController::class, 'store']);
    
    // Actualizar Registro de Diseño
    Route::put('/dt/registers/{reg_id}', [DtRegisterController::class, 'update']);
    
    // Eliminar Registro de Diseño (Físico)
    Route::delete('/dt/registers/{reg_id}', [DtRegisterController::class, 'destroy']);

    // Cerrar Fase (DT)
    Route::patch('/requirements/{id}/dt/close-phase', [DtRoleController::class, 'closePhase']);

  });

    

    /*
    |--------------------------------------------------------------------------
    | OTRAS RUTAS DE GESTIÓN DE REQUERIMIENTOS
    |--------------------------------------------------------------------------
    */
    // Mostrar Dashboard de Progreso
    Route::get('/requirements/{requirementId}/progress-dashboard', [ProgressDashboardController::class, 'show']);

    Route::middleware(['role:Admin,Coord,ConsCSPE'])->group(function () {
      // Actualizar Tipo de Gestión
      Route::patch('/requirements/{requirementId}/management-type', UpdateManagementTypeController::class);
    });
    

});


