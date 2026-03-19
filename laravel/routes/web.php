<?php

use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\BucketController;
use App\Http\Controllers\CryptoController;
use App\Http\Controllers\CsvTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PayrollController;
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
Route::post('/transactions/{id}/assign-bucket', [TransactionController::class, 'assignBucket']);
Route::post('/transactions/create-pattern', [TransactionController::class, 'createPattern']);

// CSV Imports (global)
Route::get('/import', [ImportController::class, 'create']);
Route::post('/import', [ImportController::class, 'upload']);
Route::post('/import/process', [ImportController::class, 'process']);
Route::delete('/imports/{id}', [ImportController::class, 'destroy']);

// Legacy import route — redirect to global
Route::get('/tax-years/{year}/import', [ImportController::class, 'createLegacy']);

// Balance Sheet
Route::get('/tax-years/{year}/balance-sheet', [BalanceSheetController::class, 'index']);
Route::post('/tax-years/{year}/balance-sheet', [BalanceSheetController::class, 'store']);
Route::get('/tax-years/{year}/balance-sheet/copy', [BalanceSheetController::class, 'copyPreview']);
Route::post('/tax-years/{year}/balance-sheet/copy', [BalanceSheetController::class, 'copyProcess']);
Route::post('/tax-years/{year}/balance-sheet/fetch-prices', [BalanceSheetController::class, 'fetchPrices']);
Route::patch('/balance-sheet/{id}', [BalanceSheetController::class, 'update']);
Route::delete('/balance-sheet/{id}', [BalanceSheetController::class, 'destroy']);

// Bucket Groups
Route::post('/bucket-groups', [BucketController::class, 'storeGroup']);
Route::delete('/bucket-groups/{id}', [BucketController::class, 'destroyGroup']);

// Buckets
Route::get('/buckets', [BucketController::class, 'index']);
Route::post('/buckets', [BucketController::class, 'store']);
Route::patch('/buckets/{id}/group', [BucketController::class, 'updateGroup']);
Route::delete('/buckets/{id}', [BucketController::class, 'destroy']);

// Bucket Patterns
Route::post('/buckets/{id}/patterns', [BucketController::class, 'addPattern']);
Route::delete('/patterns/{id}', [BucketController::class, 'deletePattern']);

// CSV Templates
Route::get('/csv-templates', [CsvTemplateController::class, 'index']);
Route::delete('/csv-templates/{id}', [CsvTemplateController::class, 'destroy']);

// Payroll
Route::get('/payroll', [PayrollController::class, 'index']);
Route::get('/payroll/{year}', [PayrollController::class, 'show']);
Route::post('/payroll/{year}/toggle-officer', [PayrollController::class, 'toggleOfficer']);
Route::delete('/payroll/imports/{id}', [PayrollController::class, 'destroyImport']);

// Crypto
Route::get('/crypto', [CryptoController::class, 'index']);
Route::post('/crypto', [CryptoController::class, 'store']);
Route::delete('/crypto/buys/{id}', [CryptoController::class, 'deleteBuy']);
Route::delete('/crypto/sells/{id}', [CryptoController::class, 'deleteSell']);
Route::get('/crypto/sells/{id}/allocate', [CryptoController::class, 'allocateSell']);
Route::post('/crypto/sells/{id}/allocate', [CryptoController::class, 'storeAllocation']);
Route::get('/crypto/{id}', [CryptoController::class, 'show']);
Route::delete('/crypto/{id}', [CryptoController::class, 'destroy']);
Route::post('/crypto/{id}/buys', [CryptoController::class, 'storeBuy']);
Route::get('/crypto/{id}/sell', [CryptoController::class, 'createSell']);
Route::post('/crypto/{id}/sells', [CryptoController::class, 'storeSell']);
Route::get('/crypto/{id}/import', [CryptoController::class, 'importForm']);
Route::post('/crypto/{id}/import', [CryptoController::class, 'importProcess']);