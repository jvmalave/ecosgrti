<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Security\Controllers\AuthController;


// Agrupamos por dominio 
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);});
