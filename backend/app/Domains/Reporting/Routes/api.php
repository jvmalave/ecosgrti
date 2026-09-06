<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Reporting\Http\Controllers\ReportController;

Route::prefix('reports')->middleware(['auth:api'])->group(function () {

  Route::middleware(['role:Admin,Coord,Gerente'])->group(function () {

    Route::get('/test', [ReportController::class, 'generateTestReport']);

  });
    
});