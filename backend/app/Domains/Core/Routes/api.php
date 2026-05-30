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
    
});