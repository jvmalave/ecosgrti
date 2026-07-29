<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Catalogs\Http\Controllers\CatalogController;
use App\Domains\Catalogs\Http\Controllers\OrgStructureController;

// Todas las rutas aquí heredan automáticamente el prefijo 'api' desde el proveedor global.
Route::prefix('catalogs')->group(function () {

    // Rutas públicas o de consulta general para el flujo operativo
    Route::get('/requirement-types', [CatalogController::class, 'getRequirementTypes']);
    Route::get('/management-types', [CatalogController::class, 'getManagementTypes']);

    // Mantenimiento de Estructura Organizacional (US39) - Exclusivo para Administrador (rol: admin)
    Route::middleware(['auth:api', 'role:admin'])->prefix('org-structure')->group(function () {
        
        // Obtención del árbol jerárquico respaldado por Redis
        Route::get('/tree', [OrgStructureController::class, 'tree']);

        // Altas de Nodos Jerárquicos
        Route::post('/societies', [OrgStructureController::class, 'storeSociety']);
        Route::post('/systems', [OrgStructureController::class, 'storeSystem']);
        Route::post('/requesting-units', [OrgStructureController::class, 'storeRequestingUnit']);

        // Actualización de Nodos Jerárquicos
        Route::put('/societies/{id}', [OrgStructureController::class, 'updateSociety']);
        Route::put('/systems/{id}', [OrgStructureController::class, 'updateSystem']);
        Route::put('/requesting-units/{id}', [OrgStructureController::class, 'updateRequestingUnit']);

        // Inactivación Lógica (Soft Delete) / Reactivación con Validación Restrictiva (FS-02 y FS-03)
        Route::patch('/{nodeType}/{id}/status', [OrgStructureController::class, 'updateStatus']);
    });
});