<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Http\Controllers\RequirementController;
use App\Domains\Core\Http\Controllers\RequirementClosureController;


/*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos)
|--------------------------------------------------------------------------
*/

Route::prefix('core')->middleware('auth:api')->group(function () {

/*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos) - Para Todos los Roles
|--------------------------------------------------------------------------
*/

  // Carga híbrida del Dashboard
  Route::get('requirements', [RequirementController::class, 'index']);

  /*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos) - Coordinadores y Administradores
|--------------------------------------------------------------------------
*/

  Route::middleware('role:Coord,Admin')->group(function () {
     // Crear un nuevo requerimiento
    Route::post('requirements', [RequirementController::class, 'store']);
    
    // Actualizar requerimiento (Exclusivo Coordinador/Admin)
    Route::put('/requirements/{id}', [RequirementController::class, 'update']);

      // Borrado Lógico (Consumo de ticket y Soft Delete)
    Route::delete('/requirements/{id}', [RequirementController::class, 'destroy']);

  });

  /*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos) - Consultores y Coordinadores
|--------------------------------------------------------------------------
*/

  Route::middleware('role:ConsCSPE,Coord,Admin')->group(function () {
    // Obtener los datos (RRTI) para hidratar la vista
    Route::get('/requirements/{id}/estimation', [RequirementController::class, 'showEstimation']);

    // Mostrar detalle completo (Permitido para Consultores y Coordinadores)
    Route::get('/requirements/{id}', [RequirementController::class, 'show']);

    // Guardar Borrador de Estimación
    Route::put('/requirements/{id}/estimation', [RequirementController::class, 'saveEstimationDraft']);


    // Validar clave especial y obtener ticket de Redis
    Route::post('/requirements/special-operations/validate-key', [RequirementController::class, 'requestDeletionTicket']);

    // Configurar PIN por primera vez
    Route::post('/requirements/special-operations/setup-pin', [RequirementController::class, 'setupPin']);


    // Cerrar la fase de Planificación (Hard Gate)
    Route::patch('/requirements/{id}/close-planning', [RequirementController::class, 'closePlanning']);
  });


  // ==========================================
    // FASE CIERRE  (Finalizacion Ciclo de Vida del Requerimiento)-US37
    // ==========================================

    Route::middleware(['role:Admin,Coord'])->group(function () {
    // Endpoint para generar el Acta Borrador (Etapa 1 del Cierre)
      Route::post('/requirements/{requirement}/generate-closure-act', [RequirementClosureController::class, 'generateDraft']);
      // Endpoint para generar el Acta de Cierre (Etapa 2 del Cierre)
      Route::post('/requirements/{requirement}/finalize-closure', [RequirementClosureController::class, 'finalizeClosure']);

      
    
      });


});
