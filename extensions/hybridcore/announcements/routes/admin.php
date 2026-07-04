<?php

use Hybridcore\Announcements\Http\Controllers\Admin\AnnouncementController;
use Hybridcore\Announcements\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

// All admin routes are automatically prefixed with /admin and wrapped with
// web + auth + admin middleware by the core ExtensionServiceProvider.

Route::middleware('perm:announcements.view')->group(function (): void {
    Route::get('/announcements', [AnnouncementController::class, 'index'])
        ->name('admin.announcements.index');
});

Route::middleware('perm:announcements.manage')->group(function (): void {
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])
        ->name('admin.announcements.create');

    Route::post('/announcements', [AnnouncementController::class, 'store'])
        ->name('admin.announcements.store');

    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])
        ->name('admin.announcements.edit');

    Route::patch('/announcements/{announcement}', [AnnouncementController::class, 'update'])
        ->name('admin.announcements.update');

    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
        ->name('admin.announcements.destroy');

    // Settings: /admin/settings/extensions/announcements
    Route::get('/settings/extensions/announcements', [SettingsController::class, 'show'])
        ->name('admin.settings.extensions.announcements');

    Route::patch('/settings/extensions/announcements', [SettingsController::class, 'update'])
        ->name('admin.settings.extensions.announcements.update');
});
