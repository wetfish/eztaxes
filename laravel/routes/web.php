<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TaxYearController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [DashboardController::class, 'index']);

// Tax Years
Route::post('/tax-years', [TaxYearController::class, 'store']);
Route::get('/tax-years/{year}', [TaxYearController::class, 'show']);

// CSV Imports
Route::get('/tax-years/{year}/import', [ImportController::class, 'create']);
Route::post('/tax-years/{year}/import', [ImportController::class, 'upload']);
Route::post('/tax-years/{year}/import/process', [ImportController::class, 'process']);

// Delete Import
Route::delete('/imports/{id}', [ImportController::class, 'destroy']);