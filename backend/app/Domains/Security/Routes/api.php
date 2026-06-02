<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Security\Http\Controllers\AuthController; 
use App\Domains\Security\Http\Controllers\FunctionalConsultantController;
use App\Domains\Security\Http\Controllers\ConsultantController;

/*
|--------------------------------------------------------------------------
| API Routes - Dominio Security
|--------------------------------------------------------------------------
*/

// 1. Rutas Públicas (No requieren Token)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// 2. Rutas Privadas (Requieren Token de Autenticación)
Route::middleware('auth:api')->group(function () {
    
    // Logout
    Route::post('auth/logout', [AuthController::class, 'logout']);
    
    // Endpoint de tu Dashboard (Protegido por Rol Admin)
    Route::get('dashboard', function () {
        return response()->json(['mensaje' => 'Bienvenido al Dashboard']);
    })->middleware('role:admin');

    // ==============================================================
    // SECCIÓN CONSULTORES (US04 y Selects del Frontend)
    // ==============================================================
    Route::prefix('consultores')->group(function () {
        
        // US04: Autocompletado Atómico del Grafo Organizacional (Existente)
        Route::get('lookup-organizacional/{personaId}', [FunctionalConsultantController::class, 'lookupOrganizacional']);
        
        // Nuevos Endpoints: Listas para los Selects del Formulario
        Route::get('functional-consultants', [ConsultantController::class, 'getFunctionalConsultants']);
        Route::get('cspe-consultants', [ConsultantController::class, 'getCspeConsultants']);
        
    });
});