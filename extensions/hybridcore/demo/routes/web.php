<?php

use Hybridcore\Demo\Http\Controllers\Web\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/demo', [DemoController::class, 'index'])->name('demo.index');
