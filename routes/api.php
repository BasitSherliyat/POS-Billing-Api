<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\SalesInvoiceController;
use App\Http\Controllers\Api\SalesInvoiceItemController;
use App\Http\Controllers\Api\StoreSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Route::post('auth/signup', [AuthController::class, 'signup'])->middleware('throttle:6,1');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:api')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::middleware('user.type:super admin,admin')->group(function (): void {
        Route::post('categories', [CategoryController::class, 'store']);
        Route::post('store-settings', [StoreSettingController::class, 'update']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::patch('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
        Route::post('items/upload-image', [ItemController::class, 'uploadImage']);
        Route::post('items/bulk-upload', [ItemController::class, 'bulkUpload']);
        Route::post('items', [ItemController::class, 'store']);
        Route::put('items/{item}', [ItemController::class, 'update']);
        Route::patch('items/{item}', [ItemController::class, 'update']);
        Route::delete('items/{item}', [ItemController::class, 'destroy']);
        Route::delete('sales-invoices/{sales_invoice}', [SalesInvoiceController::class, 'destroy']);
        Route::put('sales-invoice-items/{sales_invoice_item}', [SalesInvoiceItemController::class, 'update']);
        Route::patch('sales-invoice-items/{sales_invoice_item}', [SalesInvoiceItemController::class, 'update']);
        Route::delete('sales-invoice-items/{sales_invoice_item}', [SalesInvoiceItemController::class, 'destroy']);

    });

    Route::middleware('user.type:super admin,admin,staff')->group(function (): void {
        Route::get('store-settings', [StoreSettingController::class, 'show']);
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
        Route::get('items', [ItemController::class, 'index']);
        Route::get('items/{item}', [ItemController::class, 'show']);
        Route::apiResource('customers', CustomerController::class)->except(['destroy']);
        Route::apiResource('sales-invoices', SalesInvoiceController::class)->except(['destroy']);
        Route::apiResource('sales-invoice-items', SalesInvoiceItemController::class)->only(['index', 'store', 'show']);
        Route::post('/barcode/1d', [BarcodeController::class, 'generate1D']);
        Route::post('/barcode/2d', [BarcodeController::class, 'generate2D']);

    });

    Route::middleware('user.type:super admin')->group(function (): void {
        Route::apiResource('users', UserController::class);
    });
});
