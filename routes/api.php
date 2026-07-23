<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LostPetReportController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::get('/stats', [StatsController::class, 'index'])->middleware('throttle:public-api');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active', 'throttle:authenticated-api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::prefix('reports')->group(function () {
    Route::get('/', [LostPetReportController::class, 'index'])->middleware('throttle:public-api');
    Route::get('/{id}', [LostPetReportController::class, 'show'])
        ->middleware('throttle:public-api')
        ->whereUuid('id');

    Route::middleware(['auth:sanctum', 'active', 'throttle:authenticated-api'])->group(function () {
        Route::post('/', [LostPetReportController::class, 'store'])->middleware('throttle:uploads');
        Route::put('/{id}', [LostPetReportController::class, 'update'])->whereUuid('id');
        Route::delete('/{id}', [LostPetReportController::class, 'destroy'])->whereUuid('id');
        Route::post('/{id}/capture', [LostPetReportController::class, 'capture'])
            ->middleware('throttle:uploads')
            ->whereUuid('id');
    });
});

Route::middleware(['auth:sanctum', 'active', 'admin', 'throttle:authenticated-api'])->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::delete('/reports/{id}', [AdminController::class, 'deleteReport'])->whereUuid('id');
});
