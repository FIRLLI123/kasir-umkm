<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerGroupController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::put('/companies/{id}', [CompanyController::class, 'update']);
    Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    Route::get('/customer-groups', [CustomerGroupController::class, 'index']);
    Route::post('/customer-groups', [CustomerGroupController::class, 'store']);
    Route::put('/customer-groups/{id}', [CustomerGroupController::class, 'update']);
    Route::delete('/customer-groups/{id}', [CustomerGroupController::class, 'destroy']);

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
    Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update']);
    Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);

    Route::get('/app-settings', [AppSettingController::class, 'index']);
    Route::put('/app-settings', [AppSettingController::class, 'update']);

    Route::get('/sales', [SalesController::class, 'index']);
    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/sales/{id}', [SalesController::class, 'show']);
    Route::post('/sales/{id}/void', [SalesController::class, 'void']);
    Route::get('/sales/{id}/receipt', [SalesController::class, 'receipt']);

    Route::get('/stocks', [StockController::class, 'index']);
    Route::post('/stocks/adjustments', [StockController::class, 'adjust']);
    Route::get('/stocks/{productId}/history', [StockController::class, 'history']);

    Route::get('/reports/daily', [ReportController::class, 'daily']);
    Route::get('/reports/weekly', [ReportController::class, 'weekly']);
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/products', [ReportController::class, 'products']);
    Route::get('/reports/customers', [ReportController::class, 'customers']);
    Route::get('/reports/payment-methods', [ReportController::class, 'paymentMethods']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
