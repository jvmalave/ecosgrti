<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Http\Controllers\RequirementController;


/*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos)
|--------------------------------------------------------------------------
*/

Route::prefix('core')->middleware('auth:api')->group(function () {

  // Carga híbrida del Dashboard
  Route::get('requirements', [RequirementController::class, 'index']);

  // Momento 1: Crear un nuevo requerimiento
  Route::post('requirements', [RequirementController::class, 'store']);

  // Obtener los datos (RRTI) para hidratar la vista
  Route::get('/requirements/{id}/estimation', [RequirementController::class, 'showEstimation'])
      ->middleware('role:Consultant,Coord,Admin');

  // Mostrar detalle completo (Permitido para Consultores y Coordinadores)
  Route::get('/requirements/{id}', [RequirementController::class, 'show'])
    ->middleware('role:Consultant,Coord,Admin');

  // Actualizar requerimiento (Exclusivo Coordinador/Admin)
  Route::put('/requirements/{id}', [RequirementController::class, 'update'])
    ->middleware('role:Coord,Admin');

      
  // Guardar Borrador de Estimación
  Route::put('/requirements/{id}/estimation', [RequirementController::class, 'saveEstimationDraft'])
    ->middleware('role:Consultant,Coord,Admin');


  // Validar clave especial y obtener ticket de Redis
  Route::post('/requirements/special-operations/validate-key', [RequirementController::class, 'requestDeletionTicket'])
    ->middleware('role:Consultant,Coord,Admin');

  // Configurar PIN por primera vez
  Route::post('/requirements/special-operations/setup-pin', [RequirementController::class, 'setupPin'])
    ->middleware('role:Consultant,Coord,Admin');

  // Borrado Lógico (Consumo de ticket y Soft Delete)
  Route::delete('/requirements/{id}', [RequirementController::class, 'destroy'])
    ->middleware('role:Admin,Coord');

  // Cerrar la fase de Planificación (Hard Gate)
  Route::patch('/requirements/{id}/close-planning', [RequirementController::class, 'closePlanning'])
    ->middleware('role:Coord,Consultant,Admin');




});
