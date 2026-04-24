<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/token', [AuthController::class, 'token']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::post('/events/{event}/recalculate-price', [EventController::class, 'recalculatePrice']);
        Route::post('/events/{event}/program-points/reorder', [EventController::class, 'reorderProgramPoints']);

        Route::get('/tasks/board', [TaskController::class, 'board']);
        Route::post('/tasks/{task}/move', [TaskController::class, 'move']);

        Route::get('/notifications/counts', [NotificationController::class, 'counts']);
    });
});
