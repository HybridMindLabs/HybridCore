<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContentReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailController;
use App\Http\Controllers\Admin\ExtensionController;
use App\Http\Controllers\Admin\GameController;
use App\Http\Controllers\Admin\IpBanController;
use App\Http\Controllers\Admin\LegalController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\NewsArticleController;
use App\Http\Controllers\Admin\NewsCategoryController;
use App\Http\Controllers\Admin\NewsCommentController;
use App\Http\Controllers\Admin\NewsMediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RuleController;
use App\Http\Controllers\Admin\ServerController as AdminServerController;
use App\Http\Controllers\Admin\ServerReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Web\LocaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Prefixed /admin, named admin.*
| Protected by: EnsureAppIsInstalled, auth, EnsureIsAdmin.
| Each group additionally enforces a permission via the `perm` middleware.
| Super admins (is_admin) pass every permission check.
*/

Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

// Locale switching (admin)
Route::post('/locale', [LocaleController::class, 'update'])->name('admin.locale.update');

// Profile — any authenticated admin
Route::get('/profile', [ProfileController::class, 'edit'])->name('admin.profile.edit');
Route::put('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');

// Users
Route::middleware('perm:users.view')->group(function (): void {
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('admin.users.show');
});
Route::middleware('perm:users.manage')->group(function (): void {
    Route::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::post('/users/bulk', [UserController::class, 'bulkAction'])->name('admin.users.bulk');
    Route::get('/users/export', [UserController::class, 'export'])->name('admin.users.export');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('admin.users.impersonate');
    Route::post('/users/{user}/notes', [UserController::class, 'storeNote'])->name('admin.users.notes.store');
    Route::delete('/users/{user}/notes/{note}', [UserController::class, 'destroyNote'])->name('admin.users.notes.destroy');

    Route::get('/ip-bans', [IpBanController::class, 'index'])->name('admin.ip-bans.index');
    Route::post('/ip-bans', [IpBanController::class, 'store'])->name('admin.ip-bans.store');
    Route::delete('/ip-bans/{ipBan}', [IpBanController::class, 'destroy'])->name('admin.ip-bans.destroy');
});

// Roles & Permissions
Route::middleware('perm:roles.view')->group(function (): void {
    Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
});
Route::middleware('perm:roles.manage')->group(function (): void {
    Route::get('/roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
});

// Settings
Route::middleware('perm:settings.view')->group(function (): void {
    Route::get('/settings', [SettingController::class, 'index'])->name('admin.settings.index');
});
Route::middleware('perm:settings.manage')->group(function (): void {
    Route::put('/settings', [SettingController::class, 'update'])->name('admin.settings.update');
    Route::post('/settings/test-email', [SettingController::class, 'testEmail'])->name('admin.settings.test-email');
    Route::get('/legal', [LegalController::class, 'index'])->name('admin.legal.index');
    Route::get('/legal/create', [LegalController::class, 'create'])->name('admin.legal.create');
    Route::post('/legal', [LegalController::class, 'store'])->name('admin.legal.store');
    Route::get('/legal/{slug}/edit', [LegalController::class, 'edit'])->name('admin.legal.edit');
    Route::put('/legal/{slug}', [LegalController::class, 'update'])->name('admin.legal.update');
    Route::delete('/legal/{slug}', [LegalController::class, 'destroy'])->name('admin.legal.destroy');
});

// Contact messages
Route::middleware('perm:settings.view')->group(function (): void {
    Route::get('/contact', [ContactController::class, 'index'])->name('admin.contact.index');
    Route::get('/contact/{contact}', [ContactController::class, 'show'])->name('admin.contact.show');
});
Route::middleware('perm:settings.manage')->group(function (): void {
    Route::post('/contact/{contact}/reply', [ContactController::class, 'reply'])->name('admin.contact.reply');
    Route::delete('/contact/{contact}', [ContactController::class, 'destroy'])->name('admin.contact.destroy');
});

// Rules
Route::middleware('perm:settings.view')->group(function (): void {
    Route::get('/rules', [RuleController::class, 'index'])->name('admin.rules.index');
});
Route::middleware('perm:settings.manage')->group(function (): void {
    Route::get('/rules/create', [RuleController::class, 'create'])->name('admin.rules.create');
    Route::post('/rules', [RuleController::class, 'store'])->name('admin.rules.store');
    Route::get('/rules/{slug}/edit', [RuleController::class, 'edit'])->name('admin.rules.edit');
    Route::put('/rules/{slug}', [RuleController::class, 'update'])->name('admin.rules.update');
    Route::delete('/rules/{slug}', [RuleController::class, 'destroy'])->name('admin.rules.destroy');
});

// Analytics
Route::middleware('perm:analytics.view')->group(function (): void {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('admin.analytics.index');
});

// Activity Log + System Logs + read-only system pages
Route::middleware('perm:system.view')->group(function (): void {
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('admin.activity-log.index');
    Route::get('/system-logs', [SystemLogController::class, 'index'])->name('admin.system-logs.index');
    Route::get('/system-logs/download', [SystemLogController::class, 'download'])->name('admin.system-logs.download');
    Route::post('/system-logs/clear', [SystemLogController::class, 'clear'])->name('admin.system-logs.clear');
    Route::get('/system-health', [SystemHealthController::class, 'index'])->name('admin.system-health.index');
    Route::get('/updates', [UpdateController::class, 'index'])->name('admin.updates.index');
    Route::get('/backup', [BackupController::class, 'index'])->name('admin.backup.index');
});

// System management actions
Route::middleware('perm:system.manage')->group(function (): void {
    Route::post('/system-health/clear-cache', [SystemHealthController::class, 'clearCache'])->name('admin.system-health.clear-cache');
    Route::post('/system-health/clear-config', [SystemHealthController::class, 'clearConfigCache'])->name('admin.system-health.clear-config');
    Route::post('/system-health/clear-routes', [SystemHealthController::class, 'clearRouteCache'])->name('admin.system-health.clear-routes');
    Route::put('/updates/channel', [UpdateController::class, 'updateChannel'])->name('admin.updates.channel');
    Route::post('/updates/check', [UpdateController::class, 'check'])->name('admin.updates.check');
    Route::post('/updates/apply', [UpdateController::class, 'apply'])->name('admin.updates.apply');

    // Maintenance mode
    Route::post('/maintenance/enable', [MaintenanceController::class, 'enable'])->name('admin.maintenance.enable');
    Route::post('/maintenance/disable', [MaintenanceController::class, 'disable'])->name('admin.maintenance.disable');

    // Backup exports (rate limited — sensitive)
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::get('/backup/export/all', [BackupController::class, 'generateBackup'])->name('admin.backup.export.all');
        Route::post('/backup/database', [BackupController::class, 'databaseBackup'])->name('admin.backup.database');
        Route::get('/backup/export/settings', [BackupController::class, 'exportSettings'])->name('admin.backup.export.settings');
        Route::get('/backup/export/extensions', [BackupController::class, 'exportExtensions'])->name('admin.backup.export.extensions');
        Route::get('/backup/export/themes', [BackupController::class, 'exportThemes'])->name('admin.backup.export.themes');
        Route::get('/backup/export/content', [BackupController::class, 'exportContent'])->name('admin.backup.export.content');
        Route::get('/backup/download/{filename}', [BackupController::class, 'downloadBackup'])->name('admin.backup.download');
        Route::post('/backup/import', [BackupController::class, 'import'])->name('admin.backup.import');
        Route::delete('/backup/{filename}', [BackupController::class, 'deleteBackup'])->name('admin.backup.delete');
    });
});

// Extensions
Route::middleware('perm:extensions.view')->group(function (): void {
    Route::get('/extensions', [ExtensionController::class, 'index'])->name('admin.extensions.index');
    Route::get('/extensions/{extension}', [ExtensionController::class, 'show'])->name('admin.extensions.show');
});
Route::middleware('perm:extensions.manage')->group(function (): void {
    Route::post('/extensions/sync', [ExtensionController::class, 'sync'])->name('admin.extensions.sync');
    Route::post('/extensions/rebuild', [ExtensionController::class, 'rebuild'])->name('admin.extensions.rebuild');
    Route::post('/extensions/import/preview', [ExtensionController::class, 'previewImport'])->name('admin.extensions.import.preview');
    Route::post('/extensions/import/confirm', [ExtensionController::class, 'confirmImport'])->name('admin.extensions.import.confirm');
    Route::post('/extensions/bulk', [ExtensionController::class, 'bulkAction'])->name('admin.extensions.bulk');
    Route::delete('/extensions/{extension}', [ExtensionController::class, 'uninstall'])->name('admin.extensions.uninstall');
    Route::post('/extensions/{extension}/enable', [ExtensionController::class, 'enable'])->name('admin.extensions.enable');
    Route::post('/extensions/{extension}/disable', [ExtensionController::class, 'disable'])->name('admin.extensions.disable');
});

// Themes
Route::middleware('perm:themes.view')->group(function (): void {
    Route::get('/themes', [ThemeController::class, 'index'])->name('admin.themes.index');
    Route::get('/themes/{theme}', [ThemeController::class, 'show'])->name('admin.themes.show');
});
Route::middleware('perm:themes.manage')->group(function (): void {
    Route::post('/themes/sync', [ThemeController::class, 'sync'])->name('admin.themes.sync');
    Route::post('/themes/{theme}/activate', [ThemeController::class, 'activate'])->name('admin.themes.activate');
    Route::post('/themes/{theme}/deactivate', [ThemeController::class, 'deactivate'])->name('admin.themes.deactivate');
});

// Email
Route::middleware('perm:email.view')->group(function (): void {
    Route::get('/email/settings', [EmailController::class, 'settings'])->name('admin.email.settings');
    Route::get('/email/templates', [EmailController::class, 'templatesIndex'])->name('admin.email.templates.index');
    Route::get('/email/templates/{template}/edit', [EmailController::class, 'templateEdit'])->name('admin.email.templates.edit');
    Route::get('/email/logs', [EmailController::class, 'logs'])->name('admin.email.logs');
});
Route::middleware('perm:email.manage')->group(function (): void {
    Route::post('/email/settings', [EmailController::class, 'saveSettings'])->name('admin.email.settings.save');
    Route::post('/email/test-connection', [EmailController::class, 'testConnection'])->name('admin.email.test-connection');
    Route::post('/email/send-test', [EmailController::class, 'sendTestEmail'])->name('admin.email.send-test');
    Route::put('/email/templates/{template}', [EmailController::class, 'templateUpdate'])->name('admin.email.templates.update');
    Route::post('/email/templates/preview', [EmailController::class, 'templatePreview'])->name('admin.email.templates.preview');
});

// Server Browser
Route::middleware('perm:servers.view')->group(function (): void {
    Route::get('/servers', [AdminServerController::class, 'index'])->name('admin.servers.index');
    Route::get('/servers/games', [GameController::class, 'index'])->name('admin.servers.games');
    Route::get('/servers/reviews', [ServerReviewController::class, 'index'])->name('admin.servers.reviews');
});
Route::middleware('perm:servers.manage')->group(function (): void {
    Route::post('/servers', [AdminServerController::class, 'store'])->name('admin.servers.store');
    Route::put('/servers/{server}', [AdminServerController::class, 'update'])->name('admin.servers.update');
    Route::post('/servers/{server}/refresh', [AdminServerController::class, 'refresh'])->name('admin.servers.refresh');
    Route::post('/servers/{server}/bridge-token', [AdminServerController::class, 'issueBridgeToken'])->name('admin.servers.bridge.issue');
    Route::delete('/servers/{server}/bridge-token', [AdminServerController::class, 'revokeBridgeToken'])->name('admin.servers.bridge.revoke');
    Route::delete('/servers/{server}', [AdminServerController::class, 'destroy'])->name('admin.servers.destroy');
    Route::post('/servers/bulk', [AdminServerController::class, 'bulkAction'])->name('admin.servers.bulk');

    Route::post('/servers/games', [GameController::class, 'store'])->name('admin.servers.games.store');
    Route::put('/servers/games/{game}', [GameController::class, 'update'])->name('admin.servers.games.update');
    Route::delete('/servers/games/{game}', [GameController::class, 'destroy'])->name('admin.servers.games.destroy');

    Route::delete('/servers/reviews/{review}', [ServerReviewController::class, 'destroy'])->name('admin.servers.reviews.destroy');
});

// News media
Route::middleware('perm:news.view')->group(function (): void {
    Route::get('/news/media', [NewsMediaController::class, 'index'])->name('admin.news.media.index');
});
Route::middleware('perm:news.manage')->group(function (): void {
    Route::post('/news/media/upload', [NewsMediaController::class, 'upload'])->name('admin.news.media.upload');
    Route::delete('/news/media/{filename}', [NewsMediaController::class, 'destroy'])->name('admin.news.media.destroy')->where('filename', '.+');
});

// News
Route::middleware('perm:news.view')->group(function (): void {
    Route::get('/news/articles', [NewsArticleController::class, 'index'])->name('admin.news.articles.index');
    Route::get('/news/categories', [NewsCategoryController::class, 'index'])->name('admin.news.categories.index');
    Route::get('/news/comments', [NewsCommentController::class, 'index'])->name('admin.news.comments.index');
});
Route::middleware('perm:news.manage')->group(function (): void {
    Route::delete('/news/comments/{comment}', [NewsCommentController::class, 'destroy'])->name('admin.news.comments.destroy');
    Route::post('/news/comments/bulk-delete', [NewsCommentController::class, 'bulkDestroy'])->name('admin.news.comments.bulk');
});

// ── Content reports ────────────────────────────────────────────
Route::middleware('perm:content.view')->group(function (): void {
    Route::get('/moderation', [ModerationController::class, 'index'])->name('admin.moderation.index');
    Route::get('/trash', [TrashController::class, 'index'])->name('admin.trash.index');
    Route::get('/reports', [ContentReportController::class, 'index'])->name('admin.reports.index');
});
Route::middleware('perm:content.manage')->group(function (): void {
    Route::post('/reports/{report}/resolve', [ContentReportController::class, 'resolve'])->name('admin.reports.resolve');
    Route::post('/trash/articles/{id}/restore', [TrashController::class, 'restoreArticle'])->name('admin.trash.articles.restore');
    Route::delete('/trash/articles/{id}', [TrashController::class, 'forceDeleteArticle'])->name('admin.trash.articles.force-delete');
    Route::post('/trash/comments/{id}/restore', [TrashController::class, 'restoreComment'])->name('admin.trash.comments.restore');
    Route::delete('/trash/comments/{id}', [TrashController::class, 'forceDeleteComment'])->name('admin.trash.comments.force-delete');
    Route::delete('/reports/{report}/content', [ContentReportController::class, 'destroyContent'])->name('admin.reports.destroy-content');
});
Route::middleware('perm:news.manage')->group(function (): void {
    Route::get('/news/articles/create', [NewsArticleController::class, 'create'])->name('admin.news.articles.create');
    Route::post('/news/articles', [NewsArticleController::class, 'store'])->name('admin.news.articles.store');
    Route::get('/news/articles/{article}/edit', [NewsArticleController::class, 'edit'])->name('admin.news.articles.edit');
    Route::put('/news/articles/{article}', [NewsArticleController::class, 'update'])->name('admin.news.articles.update');
    Route::delete('/news/articles/{article}', [NewsArticleController::class, 'destroy'])->name('admin.news.articles.destroy');
    Route::post('/news/articles/bulk', [NewsArticleController::class, 'bulkAction'])->name('admin.news.articles.bulk');

    Route::get('/news/categories/create', [NewsCategoryController::class, 'create'])->name('admin.news.categories.create');
    Route::post('/news/categories', [NewsCategoryController::class, 'store'])->name('admin.news.categories.store');
    Route::get('/news/categories/{category}/edit', [NewsCategoryController::class, 'edit'])->name('admin.news.categories.edit');
    Route::put('/news/categories/{category}', [NewsCategoryController::class, 'update'])->name('admin.news.categories.update');
    Route::delete('/news/categories/{category}', [NewsCategoryController::class, 'destroy'])->name('admin.news.categories.destroy');
});

// Pages + Menus (Content)
Route::middleware('perm:content.view')->group(function (): void {
    Route::get('/pages', [PageController::class, 'index'])->name('admin.pages.index');
    Route::get('/menus', [MenuController::class, 'index'])->name('admin.menus.index');
});
Route::middleware('perm:content.manage')->group(function (): void {
    Route::get('/pages/create', [PageController::class, 'create'])->name('admin.pages.create');
    Route::post('/pages', [PageController::class, 'store'])->name('admin.pages.store');
    Route::get('/pages/{page}/edit', [PageController::class, 'edit'])->name('admin.pages.edit');
    Route::put('/pages/{page}', [PageController::class, 'update'])->name('admin.pages.update');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('admin.pages.destroy');

    Route::post('/menus', [MenuController::class, 'store'])->name('admin.menus.store');
    Route::get('/menus/{menu}/edit', [MenuController::class, 'edit'])->name('admin.menus.edit');
    Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('admin.menus.update');
    Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('admin.menus.destroy');
    Route::post('/menus/{menu}/items', [MenuController::class, 'storeItem'])->name('admin.menus.items.store');
    Route::put('/menus/{menu}/items/{item}', [MenuController::class, 'updateItem'])->name('admin.menus.items.update');
    Route::post('/menus/{menu}/reorder', [MenuController::class, 'reorder'])->name('admin.menus.reorder');
    Route::delete('/menus/{menu}/items/{item}', [MenuController::class, 'destroyItem'])->name('admin.menus.items.destroy');
});
