<?php

use App\Http\Controllers\Installer\InstallerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installer Routes
|--------------------------------------------------------------------------
| Accessible only when storage/installed.lock does NOT exist.
| RedirectIfInstalled middleware blocks access post-install.
*/

Route::get('/', [InstallerController::class, 'welcome'])->name('installer.welcome');
Route::get('/requirements', [InstallerController::class, 'requirements'])->name('installer.requirements');
Route::get('/database', [InstallerController::class, 'database'])->name('installer.database');
Route::post('/database', [InstallerController::class, 'storeDatabase'])->name('installer.database.store')->middleware('throttle:10,1');
Route::get('/admin', [InstallerController::class, 'adminAccount'])->name('installer.admin');
Route::post('/admin', [InstallerController::class, 'storeAdminAccount'])->name('installer.admin.store')->middleware('throttle:10,1');
Route::get('/settings', [InstallerController::class, 'settings'])->name('installer.settings');
Route::post('/settings', [InstallerController::class, 'storeSettings'])->name('installer.settings.store')->middleware('throttle:10,1');
Route::get('/finish', [InstallerController::class, 'finish'])->name('installer.finish');
Route::post('/finish', [InstallerController::class, 'complete'])->name('installer.finish.complete')->middleware('throttle:10,1');
