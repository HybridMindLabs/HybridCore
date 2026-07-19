<?php

use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\Auth\EmailVerificationController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\OAuthController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\Auth\RegisterController;
use App\Http\Controllers\Web\Auth\SocialAuthController;
use App\Http\Controllers\Web\BlockController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\ContentReportController;
use App\Http\Controllers\Web\FollowController;
use App\Http\Controllers\Web\GameServerController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\LocaleController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\MembersController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\NewsCommentController;
use App\Http\Controllers\Web\NewsController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\RuleController as WebRuleController;
use App\Http\Controllers\Web\SeoController;
use App\Http\Controllers\Web\ServerReviewController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Controllers\Web\TwoFactorController;
use App\Http\Middleware\EnsureAppIsInstalled;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Services\Localization\LocaleService;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Public-facing website + public authentication.
| Admin routes live in routes/admin.php (prefix /admin).
| Installer routes live in routes/installer.php (prefix /install).
| Extension-registered public routes are loaded by ExtensionServiceProvider.
*/

Route::middleware([EnsureAppIsInstalled::class, EnsureNotInMaintenance::class])
    ->group(function (): void {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        // ---- 2FA challenge (between login and full auth) ----
        Route::get('/two-factor-challenge', [TwoFactorController::class, 'showChallenge'])->name('auth.2fa.challenge');
        Route::post('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->middleware('throttle:10,1')->name('auth.2fa.verify');

        // ---- Public authentication (guests) ----
        Route::middleware('guest')->group(function (): void {
            Route::get('/register', [RegisterController::class, 'create'])->name('register');
            Route::post('/register', [RegisterController::class, 'store'])
                ->middleware([HandlePrecognitiveRequests::class, 'throttle:register']);

            Route::get('/login', [LoginController::class, 'create'])->name('login');
            Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

            Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
            Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
            Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
        });

        // ---- Authenticated ----
        Route::middleware('auth')->group(function (): void {
            Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

            Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');

            // Email verification
            Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
            Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:3,1')->name('verification.send');

            // Account
            Route::get('/account', [AccountController::class, 'index'])->name('account.index');
            Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update')
                ->middleware([HandlePrecognitiveRequests::class, 'throttle:account-forms']);
            Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update')
                ->middleware([HandlePrecognitiveRequests::class, 'throttle:account-forms']);
            Route::put('/account/preferences', [AccountController::class, 'updatePreferences'])->name('account.preferences.update');
            Route::put('/account/email-preferences', [AccountController::class, 'updateEmailPreferences'])->name('account.email-preferences.update');

            // Connected accounts
            Route::delete('/account/connected-accounts/{provider}', [OAuthController::class, 'disconnect'])
                ->name('oauth.disconnect');

            // Sessions
            Route::delete('/account/sessions/{session}', [SessionController::class, 'destroy'])->name('account.sessions.destroy');
            Route::delete('/account/sessions', [SessionController::class, 'destroyOthers'])->name('account.sessions.destroy-others');

            // 2FA
            Route::post('/account/two-factor/setup', [TwoFactorController::class, 'setup'])->name('account.2fa.setup');
            // Throttled like the login challenge is: a 6-digit code is only a
            // million guesses, and confirm() had no limit at all.
            Route::post('/account/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('account.2fa.confirm');
            Route::delete('/account/two-factor', [TwoFactorController::class, 'disable'])->name('account.2fa.disable');
            Route::post('/account/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateCodes'])->name('account.2fa.recovery-codes');

            // Avatar & banner
            Route::post('/account/avatar', [MediaController::class, 'uploadAvatar'])->name('account.avatar.upload')->middleware('throttle:10,1');
            Route::delete('/account/avatar', [MediaController::class, 'deleteAvatar'])->name('account.avatar.delete');
            Route::post('/account/banner', [MediaController::class, 'uploadBanner'])->name('account.banner.upload')->middleware('throttle:10,1');
            Route::delete('/account/banner', [MediaController::class, 'deleteBanner'])->name('account.banner.delete');

            // Notifications
            Route::get('/account/notifications', [NotificationController::class, 'index'])->name('account.notifications');
            Route::post('/account/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('account.notifications.read');
            Route::post('/account/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('account.notifications.read-all');
            Route::delete('/account/notifications/{id}', [NotificationController::class, 'destroy'])->name('account.notifications.destroy');

            // Notifications API (polling)
            Route::get('/api/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.notifications.unread-count');
            Route::get('/api/notifications/recent', [NotificationController::class, 'recent'])->name('api.notifications.recent');

            // Messages (DM)
            Route::get('/account/messages', [MessageController::class, 'index'])->name('account.messages.index');
            Route::get('/account/messages/{conversation}', [MessageController::class, 'show'])->name('account.messages.show');
            Route::post('/account/messages', [MessageController::class, 'start'])->name('account.messages.start')->middleware('throttle:10,1');
            Route::post('/account/messages/{conversation}', [MessageController::class, 'send'])->name('account.messages.send')->middleware('throttle:30,1');
            Route::delete('/account/messages/{conversation}/messages/{message}', [MessageController::class, 'deleteMessage'])->name('account.messages.delete');

            // Blocks
            Route::get('/account/blocked', [BlockController::class, 'index'])->name('account.blocked');
            Route::post('/account/block/{user}', [BlockController::class, 'block'])->name('account.block')->middleware('throttle:20,1');
            Route::delete('/account/block/{user}', [BlockController::class, 'unblock'])->name('account.unblock')->middleware('throttle:20,1');

            // Favorites
            Route::get('/account/favorites', [AccountController::class, 'favorites'])->name('account.favorites');
            Route::post('/account/favorites/{server}', [AccountController::class, 'toggleFavorite'])->name('account.favorites.toggle');

            // Activity log
            Route::get('/account/activity', [AccountController::class, 'activityLog'])->name('account.activity');

            // Account danger zone
            Route::post('/account/delete', [AccountController::class, 'deleteAccount'])->name('account.delete');
            Route::post('/account/export', [AccountController::class, 'exportData'])->name('account.export')->middleware('throttle:3,10');
            Route::get('/account/export/{filename}', [AccountController::class, 'downloadExport'])->name('account.export.download');
        });

        // ---- Generic OAuth entry points (extensions provide implementations) ----
        Route::middleware('throttle:10,1')->group(function (): void {
            Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
            Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
        });

        // ---- Core OAuth providers: Discord, Steam ----
        Route::middleware('throttle:10,1')->group(function (): void {
            Route::get('/auth/discord/redirect', [SocialAuthController::class, 'redirect'])
                ->name('oauth.discord.redirect')->defaults('provider', 'discord');
            Route::get('/auth/discord/callback', [SocialAuthController::class, 'callback'])
                ->name('oauth.discord.callback')->defaults('provider', 'discord');

            Route::get('/auth/steam/redirect', [SocialAuthController::class, 'redirect'])
                ->name('oauth.steam.redirect')->defaults('provider', 'steam');
            Route::get('/auth/steam/callback', [SocialAuthController::class, 'callback'])
                ->name('oauth.steam.callback')->defaults('provider', 'steam');

            Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirect'])
                ->name('oauth.google.redirect')->defaults('provider', 'google');
            Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])
                ->name('oauth.google.callback')->defaults('provider', 'google');
        });

        // Steam has no email — finish account creation here.
        Route::middleware('guest')->group(function (): void {
            Route::get('/auth/complete-profile', [SocialAuthController::class, 'showCompleteProfile'])
                ->name('oauth.complete-profile');
            Route::post('/auth/complete-profile', [SocialAuthController::class, 'storeCompleteProfile'])
                ->middleware('throttle:5,1')->name('oauth.complete-profile.store');
        });

        // ---- Public community pages ----
        Route::get('/members', [MembersController::class, 'index'])->name('members.index');
        Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');
        Route::middleware('auth')->group(function (): void {
            Route::post('/u/{user}/follow', [FollowController::class, 'store'])->name('profile.follow')->middleware('throttle:30,1');
            Route::delete('/u/{user}/follow', [FollowController::class, 'destroy'])->name('profile.unfollow')->middleware('throttle:30,1');
            Route::post('/report', [ContentReportController::class, 'store'])->name('report.store')->middleware('throttle:10,1');
        });

        // ---- Server browser ----
        Route::get('/servers', [GameServerController::class, 'index'])->name('servers.index');
        Route::get('/servers/{game:slug}', [GameServerController::class, 'game'])->name('servers.game');
        Route::get('/servers/{game:slug}/{ip}/{port}', [GameServerController::class, 'show'])->name('servers.show');
        Route::get('/servers/{game:slug}/connect/{ip}/{port}', [GameServerController::class, 'connect'])->name('servers.connect');
        Route::post('/servers/{server}/favourite', [GameServerController::class, 'favourite'])->middleware(['auth', 'throttle:30,1'])->name('servers.favourite');

        Route::middleware('auth')->group(function (): void {
            Route::post('/servers/{server}/reviews', [ServerReviewController::class, 'store'])->name('servers.reviews.store')->middleware('throttle:10,1');
            Route::delete('/servers/{server}/reviews/{review}', [ServerReviewController::class, 'destroy'])->name('servers.reviews.destroy')->middleware('throttle:20,1');
        });
        // ---- News ----
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/feed.xml', [NewsController::class, 'feed'])->name('news.feed');
        Route::get('/news/category/{category:slug}', [NewsController::class, 'category'])->name('news.category');
        Route::get('/news/tag/{tag:slug}', [NewsController::class, 'tag'])->name('news.tag');
        Route::get('/news/{article:slug}', [NewsController::class, 'show'])->name('news.show');
        Route::middleware('auth')->group(function (): void {
            Route::post('/news/{article:slug}/comments', [NewsCommentController::class, 'store'])->name('news.comments.store')->middleware('throttle:10,1');
            Route::delete('/news/{article:slug}/comments/{comment}', [NewsCommentController::class, 'destroy'])->name('news.comments.destroy')->middleware('throttle:20,1');
        });

        // ---- Global search API ----
        Route::get('/api/search', SearchController::class)
            ->name('api.search')
            ->middleware('throttle:60,1');

        // ---- Locale switching ----
        Route::post('/locale', [LocaleController::class, 'update'])
            ->middleware('throttle:20,1')->name('locale.update');

        // ---- SEO ----
        Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
        Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

        // ---- Legal pages (dynamic) ----
        Route::get('/legal/{slug}', [LegalController::class, 'show'])->name('legal.show');

        // ---- Unsubscribe ----
        Route::get('/unsubscribe/{category}', [UnsubscribeController::class, 'show'])->name('unsubscribe.show');
        Route::delete('/unsubscribe/{category}', [UnsubscribeController::class, 'destroy'])->name('unsubscribe.destroy');

        // ---- Rules ----
        Route::get('/rules', [WebRuleController::class, 'index'])->name('rules.index');
        Route::get('/rules/{slug}', [WebRuleController::class, 'show'])->name('rules.show');

        // ---- Contact ----
        Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
        Route::post('/contact', [ContactController::class, 'store'])->name('contact.store')
            ->middleware([HandlePrecognitiveRequests::class, 'throttle:contact']);

        // ---- Localized SEO URLs (public pages only) ----
        // Optional /{locale}/... mirror of Home, Servers and Pages, e.g.
        // /en/servers, /bg/about. The locale segment is constrained to the
        // currently active locale codes so it can never shadow a Page whose
        // slug happens to be two letters (e.g. /us). Admin/account routes
        // never carry this segment and are completely unaffected.
        //
        // Route files are loaded on every application boot — including
        // artisan commands like package:discover that run before the
        // database exists (fresh installs, CI) — so this must never throw.
        try {
            $localeCodes = app(LocaleService::class)->supportedCodes();
        } catch (Throwable) {
            $localeCodes = ['en', 'bg'];
        }
        $localePattern = implode('|', array_map('preg_quote', $localeCodes));
        Route::prefix('{locale}')->where(['locale' => $localePattern])->group(function (): void {
            Route::get('/', [HomeController::class, 'index'])->name('home.localized');
            Route::get('/servers', [GameServerController::class, 'index'])->name('servers.index.localized');
            Route::get('/{slug}', [PageController::class, 'show'])
                ->where('slug', '^(?!admin|install|sitemap|robots|login|register|logout|account|auth|email|forgot-password|reset-password)[a-z0-9\-]+$')
                ->name('pages.show.localized');
        });

        // Published pages — keep LAST so it never shadows other routes.
        // Extension routes are registered before this catch-all, so they
        // naturally take priority. Only core-reserved prefixes are excluded.
        Route::get('/{slug}', [PageController::class, 'show'])
            ->where('slug', '^(?!admin|install|sitemap|robots|login|register|logout|account|auth|email|forgot-password|reset-password)[a-z0-9\-]+$')
            ->name('pages.show');
    });
