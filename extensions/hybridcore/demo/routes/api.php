<?php

use Hybridcore\Demo\Http\Controllers\Api\DemoController;
use Illuminate\Support\Facades\Route;

// Loaded with the "api" middleware under /api by the core.
Route::middleware(['auth:sanctum', 'abilities:demo:ping'])
    ->get('/demo/ping', [DemoController::class, 'ping']);
