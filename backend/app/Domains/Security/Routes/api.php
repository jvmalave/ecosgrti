<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Security\Http\Controllers\AuthController; 
use App\Domains\Security\Http\Controllers\FunctionalConsultantController;
use App\Domains\Security\Http\Controllers\ConsultantController;
use App\Domains\Security\Http\Controllers\UnifiedPersonController;

/*
|--------------------------------------------------------------------------
| API Routes - Dominio Security
|--------------------------------------------------------------------------
*/

// 1. Rutas Públicas (No requieren Token)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// 2. Rutas Privadas (Requieren Token de Autenticación y Contraseña Vigente)
// Agregamos 'password.expired' para proteger todo el bloque
Route::middleware(['auth:api', 'password.expired' ])->group(function () {
    
    // ==============================================================
    // SECCIÓN AUTENTICACIÓN Y SEGURIDAD
    // ==============================================================
    Route::post('auth/logout', [AuthController::class, 'logout']);
    
    // Nuevo endpoint para el cambio voluntario u obligatorio de contraseña
    Route::post('auth/change-password', [AuthController::class, 'changePassword']);
    
    // ==============================================================
    // SECCIÓN DASHBOARD
    // ==============================================================
    // Endpoint de tu Dashboard (Protegido por Rol Admin)
    Route::get('dashboard', function () {
        return response()->json(['mensaje' => 'Bienvenido al Dashboard']);
    })->middleware('role:admin');

    // ==============================================================
    // SECCIÓN MDM - GESTIÓN DE IDENTIDADES 
    // ==============================================================
    Route::prefix('mdm')->group(function () {
        // 1. Listado paginado de todas las identidades (Eager Loading)
        Route::get('persons', [UnifiedPersonController::class, 'index'])
            ->name('mdm.persons.index')
            ->middleware('role:Admin,Coord'); 

        // 2. Creación de una nueva identidad
        Route::post('persons', [UnifiedPersonController::class, 'store'])
            ->name('mdm.persons.store')
            ->middleware('role:admin,Coord'); 

        // 3. Detalle de una identidad específica (Eager Loading)
        Route::get('persons/{id}', [UnifiedPersonController::class, 'show'])
            ->name('mdm.persons.show')
            ->middleware('role:admin,Coord');

        // 4. Actualización atómica de identidad y perfiles
        Route::put('persons/{id}', [UnifiedPersonController::class, 'update'])
            ->name('mdm.persons.update')
            ->middleware('role:admin,Coord');

        // 5. Desactivación Lógica (Soft Delete)
        Route::delete('persons/{id}', [UnifiedPersonController::class, 'destroy'])
            ->name('mdm.persons.destroy')
            ->middleware('role:admin,Coord');

        // Endpoint auxiliar: Catálogo de Unidades Solicitantes
        Route::get('requesting-units', [UnifiedPersonController::class, 'getRequestingUnits']);
          
    });

    // ==============================================================
    // SECCIÓN CONSULTORES (Selects del Frontend)
    // ==============================================================
    Route::prefix('consultores')->group(function () {
        
        // Autocompletado Atómico del Grafo Organizacional (Existente)
        Route::get('lookup-organizacional/{personaId}', [FunctionalConsultantController::class, 'lookupOrganizacional']);
        
        // Nuevos Endpoints: Listas para los Selects del Formulario
        Route::get('functional-consultants', [ConsultantController::class, 'getFunctionalConsultants']);
        Route::get('cspe-consultants', [ConsultantController::class, 'getCspeConsultants']);
        
    });
});