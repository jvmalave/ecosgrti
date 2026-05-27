<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Security\Http\Controllers\AuthController; 
use App\Domains\Security\Http\Controllers\FunctionalConsultantController;

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

    // US04: Autocompletado Atómico del Grafo Organizacional
    Route::get('consultores/lookup-organizacional/{personaId}', [FunctionalConsultantController::class, 'lookupOrganizacional']);
});