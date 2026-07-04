<?php

use Hybridcore\Demo\Http\Controllers\Admin\DemoController;
use Illuminate\Support\Facades\Route;

// Loaded with web + auth + admin middleware and the /admin prefix by the core.
Route::middleware('perm:demo.view')->group(function (): void {
    Route::get('/demo', [DemoController::class, 'index'])->name('admin.demo.index');
});
