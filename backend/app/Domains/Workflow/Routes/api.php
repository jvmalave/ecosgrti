<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Workflow\Http\Controllers\ATFAgreementController; 

Route::prefix('workflow')->group(function () {
    
    // CU-015: Registrar Acuerdo ATF
    Route::post('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'store']);
    
});