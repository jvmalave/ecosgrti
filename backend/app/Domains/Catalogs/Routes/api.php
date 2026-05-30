<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Catalogs\Http\Controllers\CatalogController;

// Todas las rutas aquí heredan automáticamente el prefijo 'api' desde el proveedor global,
// así que solo declaramos el prefijo del dominio.
Route::prefix('catalogs')->group(function () {
    Route::get('/requirement-types', [CatalogController::class, 'getRequirementTypes']);
    Route::get('/management-types', [CatalogController::class, 'getManagementTypes']);
});