<?php

use App\Http\Controllers\Api\BridgeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ServerController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\AuthenticateBridgeServer;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('login', 'login');
        Route::post('register', 'register');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', 'logout');
            Route::get('me', 'me');
        });
    });

    Route::get('servers', [ServerController::class, 'index']);
    Route::get('servers/{server}', [ServerController::class, 'show']);
    Route::get('users/{username}', [UserController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
});

// ── Game-server bridge ──────────────────────────────────────────
// Consumed by the in-game plugin with a per-server bearer token.
Route::prefix('bridge')
    ->middleware([AuthenticateBridgeServer::class, 'throttle:120,1'])
    ->group(function () {
        Route::post('poll', [BridgeController::class, 'poll'])->name('api.bridge.poll');
        Route::post('ack', [BridgeController::class, 'ack'])->name('api.bridge.ack');
        Route::post('events', [BridgeController::class, 'events'])->name('api.bridge.events');
    });
