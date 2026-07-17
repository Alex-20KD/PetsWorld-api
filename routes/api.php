<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LostPetReportController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded within a "api" middleware group and given the /api
| prefix automatically by Laravel.
|
*/

// Pública — sin auth:sanctum
Route::get('/stats', [StatsController::class, 'index']);

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Lost Pet Reports
Route::prefix('reports')->group(function () {
    // Public routes
    Route::get('/', [LostPetReportController::class, 'index']);
    Route::get('/{id}', [LostPetReportController::class, 'show']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [LostPetReportController::class, 'store']);
        Route::put('/{id}', [LostPetReportController::class, 'update']);
        Route::delete('/{id}', [LostPetReportController::class, 'destroy']);
        Route::post('/{id}/capture', [LostPetReportController::class, 'capture']);
    });
});
