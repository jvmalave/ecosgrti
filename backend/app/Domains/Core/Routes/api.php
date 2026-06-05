<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Http\Controllers\RequirementController;


/*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos)
|--------------------------------------------------------------------------
*/

Route::prefix('core')->middleware('auth:api')->group(function () {
    
  // US05 - Carga híbrida del Dashboard
  Route::get('requirements', [RequirementController::class, 'index']);

  // US04 - Momento 1: Crear un nuevo requerimiento
  Route::post('requirements', [RequirementController::class, 'store']);

  // US23: Registrar Estimación
  // Protegido por RoleMiddleware: Solo Consultor, Coordinador o Admin pueden operar
  Route::post('/requirements/{id}/estimation', [RequirementController::class, 'registerEstimation'])
      ->middleware('role:Consultor,Coordinador,Admin');

  // US24: Validar clave especial y obtener ticket de Redis
    Route::post('/requirements/special-operations/validate-key', [RequirementController::class, 'requestDeletionTicket'])
        ->middleware('role:Admin,Coordinador');

  // US24: Fase 2 del Borrado Lógico (Consumo de ticket y Soft Delete)
    Route::delete('/requirements/{id}', [RequirementController::class, 'destroy'])
        ->middleware('role:Admin,Coordinador');
});