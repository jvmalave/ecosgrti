<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Security\Controllers\AuthController;


// Rutas API para Seguridad (US01: Autenticación)
// Rutas Públicas (No Requieren Token)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    });
// Rutas Privadas (Requieren Token)
Route::middleware('auth:api')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
});
