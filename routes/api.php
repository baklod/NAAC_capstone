<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProfitAnalysisInsightController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductSyncController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TruckingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/flutter/products', [ProductController::class, 'index']);
Route::get('/flutter/sales', [SaleController::class, 'historyFromFlutter']);
Route::post('/flutter/sales', [SaleController::class, 'storeFromFlutter']);

Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

Route::post('/sales', [SaleController::class, 'store']);

Route::middleware('web')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/profit-analysis/insights', [ProfitAnalysisInsightController::class, 'store']);

    Route::get('/branches', [BranchController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/inventories', [InventoryController::class, 'index']);
    Route::get('/inventories/revenue-logs', [InventoryController::class, 'revenueLogs']);
    Route::put('/inventories/{id}', [InventoryController::class, 'update']);
    Route::delete('/inventories/{id}', [InventoryController::class, 'destroy']);
    Route::post('/inventories', [InventoryController::class, 'store']);


    Route::middleware('admin')->group(function () {


        Route::get('/trucking', [TruckingController::class, 'index']);
        Route::post('/trucking', [TruckingController::class, 'store']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

        Route::get('/branches/manager-options', [BranchController::class, 'managerOptions']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{id}', [BranchController::class, 'update']);
        Route::delete('/branches/{id}', [BranchController::class, 'destroy']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);
    });
});

Route::post('/products/sync', [ProductSyncController::class, 'sync']);
