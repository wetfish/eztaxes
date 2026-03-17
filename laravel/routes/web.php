<?php

use App\Http\Controllers\BucketController;
use App\Http\Controllers\CsvTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TaxYearController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', [DashboardController::class, 'index']);

// Tax Years
Route::post('/tax-years', [TaxYearController::class, 'store']);
Route::get('/tax-years/{year}', [TaxYearController::class, 'show']);

// Transactions
Route::get('/tax-years/{year}/transactions', [TransactionController::class, 'index']);

// CSV Imports
Route::get('/tax-years/{year}/import', [ImportController::class, 'create']);
Route::post('/tax-years/{year}/import', [ImportController::class, 'upload']);
Route::post('/tax-years/{year}/import/process', [ImportController::class, 'process']);
Route::delete('/imports/{id}', [ImportController::class, 'destroy']);

// Buckets
Route::get('/buckets', [BucketController::class, 'index']);

// CSV Templates
Route::get('/csv-templates', [CsvTemplateController::class, 'index']);
Route::delete('/csv-templates/{id}', [CsvTemplateController::class, 'destroy']);