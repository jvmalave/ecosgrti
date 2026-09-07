<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Reporting\Http\Controllers\ReportController;

Route::prefix('reports')->middleware(['auth:api'])->group(function () {

  Route::middleware(['role:Admin,Coord,Gerente'])->group(function () {

    Route::get('/test', [ReportController::class, 'generateTestReport']);

    Route::get('/mdm-directory', [ReportController::class, 'generateMdmDirectory']);

    Route::get('/closure-act/{id}', [ReportController::class, 'generateClosureAct']);

    Route::get('/closure-act/rrti/{rrti}', [ReportController::class, 'generateClosureActByRrti']);

    Route::get('/audit-log', [ReportController::class, 'generateAuditLog']);

  });
    
});