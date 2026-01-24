<?php
use App\Http\Controllers\Api\ProductSyncController;
use Illuminate\Support\Facades\Route;


Route::post('/products/sync', [ProductSyncController::class, 'sync']);
