<?php

use Hybridcore\Demo\Http\Controllers\Admin\DemoController;
use Illuminate\Support\Facades\Route;

// Loaded with web + auth + admin middleware and the /admin prefix by the core.
Route::middleware('perm:demo.view')->group(function (): void {
    Route::get('/demo', [DemoController::class, 'index'])->name('admin.demo.index');
    Route::post('/demo/notify', [DemoController::class, 'notify'])->name('admin.demo.notify');
});

// Settings URL/route-name convention required by $registry->settings()->register().
Route::middleware('perm:demo.manage')->group(function (): void {
    Route::get('/settings/extensions/demo', [DemoController::class, 'settings'])
        ->name('admin.settings.extensions.demo');

    Route::patch('/settings/extensions/demo', [DemoController::class, 'updateSettings'])
        ->name('admin.settings.extensions.demo.update');
});
