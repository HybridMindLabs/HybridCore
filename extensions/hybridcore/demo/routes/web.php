<?php

use Hybridcore\Demo\Http\Controllers\Web\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/demo', [DemoController::class, 'index'])->name('demo.index');

// Auth-guarded — accountTabs() routes must own their own auth middleware,
// the core only wraps routes/web.php in the plain "web" group.
Route::middleware('auth')->get('/account/demo', [DemoController::class, 'account'])->name('account.demo.index');
