<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Reporting\Http\Controllers\ReportController;
use App\Domains\Reporting\Http\Controllers\TraceabilityController;


Route::prefix('reports')->middleware(['auth:api'])->group(function () {

  Route::middleware(['role:Admin,Coord,Gerente'])->group(function () {

    Route::get('/test', [ReportController::class, 'generateTestReport']);

    Route::get('/mdm-directory/data', [ReportController::class, 'getMdmDirectoryData']);

    Route::get('/mdm-directory/pdf', [ReportController::class, 'generateMdmDirectory']);

    Route::get('/closure-act/{id}', [ReportController::class, 'generateClosureAct']);

    Route::get('/closure-act/rrti/{rrti}', [ReportController::class, 'generateClosureActByRrti']);

    Route::get('/tracking-document/{rrti}', [ReportController::class, 'generateClosureActByRrti']);

    Route::get('/tracking-document/{rrti}/data', [ReportController::class, 'getTrackingDataByRrti']);

    Route::post('/audit-log/data', [ReportController::class, 'getAuditLogData']);
    
    Route::post('/audit-log/pdf', [ReportController::class, 'generateAuditLog']);

    Route::get('/consultant-management', [ReportController::class, 'generateConsultantManagement']);

    Route::get('/cspe-consultants', [ReportController::class, 'getCspeConsultantsList']);

    Route::get('/production-deployments', [ReportController::class, 'generateProductionDeployments']);

    Route::get('/production-deployments/data', [ReportController::class, 'getProductionDeploymentsData']);

    Route::get('/operational-sheet', [ReportController::class, 'downloadOperationalSheet']);

    Route::get('/executive-summary', [ReportController::class, 'generateExecutiveSummary']);

    Route::get('/kpi/otd', [ReportController::class, 'getOtdMetrics']);

    Route::get('/kpi/operational', [ReportController::class, 'getOperationalMetrics']);

    Route::get('/kpi/deviations', [ReportController::class, 'getDeviationMetrics']);

    Route::get('/kpi/deviation', [ReportController::class, 'getDeviationAlerts']);

    Route::get('/kpi/aging', [ReportController::class, 'getAgingMetrics']);

    Route::prefix('cspe')->group(function () {
        
        Route::get('/workload', [ReportController::class, 'getWorkload']);
        
        Route::get('/{id}/history', [ReportController::class, 'getConsultantHistory']);

        Route::get('/consolidated-general', [ReportController::class, 'generateConsolidatedGeneral']);
    });

    Route::get('/traceability/search', [TraceabilityController::class, 'searchComponent']);

    Route::get('/traceability/download-support', [TraceabilityController::class, 'downloadSupportFile']);

    Route::post('/traceability/component-pdf', [TraceabilityController::class, 'exportComponentPdf']);

  });
    
});

Route::prefix('cspe')->group(function () {
    // GET: /api/cspe/workload
    Route::get('/workload', [ReportController::class, 'getWorkload']);
    
    // GET: /api/cspe/{id}/history
    Route::get('/{id}/history', [ReportController::class, 'getConsultantHistory']);
});
