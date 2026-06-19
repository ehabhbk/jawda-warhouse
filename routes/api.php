<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ShelfController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'message' => 'نظام إدارة المخازن']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/chart-data', [DashboardController::class, 'chartData']);

    Route::get('/categories/all', [CategoryController::class, 'all']);
    Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);

    Route::get('/shelves/all', [ShelfController::class, 'all']);
    Route::apiResource('shelves', ShelfController::class)->except(['create', 'edit']);

    Route::get('/suppliers/all', [SupplierController::class, 'all']);
    Route::apiResource('suppliers', SupplierController::class)->except(['create', 'edit']);

    Route::get('/items/all', [ItemController::class, 'all']);
    Route::apiResource('items', ItemController::class)->except(['create', 'edit']);

    Route::apiResource('purchases', PurchaseController::class)->except(['create', 'edit']);

    Route::put('/orders/{order}/approve', [OrderController::class, 'approve']);
    Route::put('/orders/{order}/reject', [OrderController::class, 'reject']);
    Route::put('/orders/{order}/complete', [OrderController::class, 'complete']);
    Route::apiResource('orders', OrderController::class)->except(['create', 'edit']);

    Route::get('/stock-movements', [StockMovementController::class, 'index']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/users/all', [UserController::class, 'all']);
        Route::apiResource('users', UserController::class)->except(['create', 'edit']);
    });
});
