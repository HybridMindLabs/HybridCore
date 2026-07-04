<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
| These routes handle admin login/logout.
| Protected only by EnsureAppIsInstalled — no auth middleware.
*/

Route::get('/login', [LoginController::class, 'create'])->name('admin.login');
Route::post('/login', [LoginController::class, 'store'])->name('admin.login.store')->middleware('throttle:5,1');
Route::post('/logout', [LoginController::class, 'destroy'])->name('admin.logout')->middleware('auth');
