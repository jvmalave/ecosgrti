<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Workflow\Http\Controllers\ATFAgreementController;
use App\Domains\Workflow\Http\Controllers\ProgressDashboardController; 

Route::prefix('workflow')->group(function () {
    
    // Registrar Acuerdo ATF
    Route::post('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'store']);
    // Mostrar Dashboard de Progreso
    Route::get('/requirements/{requirementId}/progress-dashboard', [ProgressDashboardController::class, 'show']);
});