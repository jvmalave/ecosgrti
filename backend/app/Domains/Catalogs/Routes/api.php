<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Catalogs\Http\Controllers\CatalogController;
use App\Domains\Catalogs\Http\Controllers\OrgStructureController;
use App\Domains\Catalogs\Http\Controllers\ProgressMatrixController;
use App\Domains\Catalogs\Http\Controllers\MilestoneController;

// Todas las rutas aquí heredan automáticamente el prefijo 'api' desde el proveedor global.
Route::prefix('catalogs')->group(function () {

    
    // RUTAS PUBLICAS O DE CONSULTA GENERAL PARA EL FLUJO OPERATIVO
    Route::get('/requirement-types', [CatalogController::class, 'getRequirementTypes']);
    Route::get('/management-types', [CatalogController::class, 'getManagementTypes']);

    
    // MANTENIMIENTO DE ESTRUCTURA JERÁRQUICA
    Route::middleware(['auth:api', 'role:admin'])->prefix('org-structure')->group(function () {
        
        // OBTENER ESTRUCTURA JERÁRQUICA (REDIS)
        Route::get('/tree', [OrgStructureController::class, 'tree']);

        // CREACION DE NODOS JERÁRQUICOS
        Route::post('/societies', [OrgStructureController::class, 'storeSociety']);
        Route::post('/systems', [OrgStructureController::class, 'storeSystem']);
        Route::post('/requesting-units', [OrgStructureController::class, 'storeRequestingUnit']);

        // ACTUALIZACIÓN DE NODOS JERÁRQUICOS
        Route::put('/societies/{id}', [OrgStructureController::class, 'updateSociety']);
        Route::put('/systems/{id}', [OrgStructureController::class, 'updateSystem']);
        Route::put('/requesting-units/{id}', [OrgStructureController::class, 'updateRequestingUnit']);

        // INACTIVACIÓN LÓGICA DE NODOS JERÁRQUICOS 
        Route::patch('/{nodeType}/{id}/status', [OrgStructureController::class, 'updateStatus']);
    });

  
    // MANTENIMIENTO DE MATRITCES DE PROGRESO Y  MANEJO DE VERSIONES
    Route::middleware(['auth:api', 'role:admin'])->prefix('progress-matrices')->group(function () {
        
        // OBTENER TODAS LAS MATRICES DE PROGRESO
        Route::get('/active', [ProgressMatrixController::class, 'getActiveMatrix'])->name('matrix.active');
        
        // VERSIONAMIENTO INMUTABLE: PUBLICACIÓN DE NUEVA MATRIZ
        Route::post('/publish', [ProgressMatrixController::class, 'publish'])->name('matrix.publish');
    });

    // MANTENIMIENTO DE HITOS TÉCNICOS 
    Route::middleware(['auth:api', 'role:admin'])->prefix('milestones')->group(function () {
        // LISTADO DE HITOS TÉCNICOS 
        Route::get('/', [MilestoneController::class, 'index']);

        // CREACIÓN DE HITOS TÉCNICOS
        Route::post('/', [MilestoneController::class, 'store']);

        // ACTUALIZACIÓN DE HITOS TÉCNICOS
        Route::put('/{id}', [MilestoneController::class, 'update']);

        // ELIMINACIÓN DE HITOS TÉCNICOS
        Route::delete('/{id}', [MilestoneController::class, 'destroy']);
    });
});