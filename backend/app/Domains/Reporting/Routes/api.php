<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Reporting\Http\Controllers\ReportController;

Route::prefix('reports')->middleware(['auth:api'])->group(function () {

  Route::middleware(['role:Admin,Coord,Gerente'])->group(function () {

    Route::get('/test', [ReportController::class, 'generateTestReport']);

    Route::get('/mdm-directory', [ReportController::class, 'generateMdmDirectory']);

    Route::get('/mdm-directory/pdf', [ReportController::class, 'downloadMdmDirectory']);

    Route::get('/closure-act/{id}', [ReportController::class, 'generateClosureAct']);

    Route::get('/closure-act/rrti/{rrti}', [ReportController::class, 'generateClosureActByRrti']);

    Route::get('/audit-log', [ReportController::class, 'generateAuditLog']);

    Route::get('/consultant-management', [ReportController::class, 'generateConsultantManagement']);

    Route::get('/cspe-consultants', [ReportController::class, 'getCspeConsultantsList']);

    Route::get('/production-deployments', [ReportController::class, 'generateProductionDeployments']);

    Route::get('/operational-sheet', [ReportController::class, 'downloadOperationalSheet']);

    Route::get('/executive-summary', [ReportController::class, 'generateExecutiveSummary']);

    Route::get('/kpi/otd', [ReportController::class, 'getOtdMetrics']);

    Route::get('/kpi/deviation', [ReportController::class, 'getDeviationAlerts']);

    Route::get('/kpi/aging', [ReportController::class, 'getAgingMetrics']);

  });
    
});