<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Core\Http\Controllers\RequirementController;

/*
|--------------------------------------------------------------------------
| API Routes - Dominio Core (Gestión de Requerimientos)
|--------------------------------------------------------------------------
*/

Route::prefix('core')->middleware('auth:api')->group(function () {
    
    // US04 - Momento 1: Crear un nuevo requerimiento
    Route::post('requirements', [RequirementController::class, 'store']);
    
});