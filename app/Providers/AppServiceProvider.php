<?php

namespace App\Providers;

use App\Events\NotificationCreated;
use App\Models\User;
use App\Services\Auth\OAuthProviderRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Steam\SteamExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->forceSafeDriversUntilInstalled();
    }

    /**
     * Before installation completes, force drivers that don't need a working
     * database (file sessions/cache, sync queue). Otherwise a fresh clone with
     * SESSION_DRIVER=database dies on "select * from sessions" before the
     * installer can even render.
     *
     * Deliberately checks only the lock file and the .env flag — probing the
     * database here would recreate the exact failure this guards against.
     * Skipped in tests so the suite keeps its configured array/sync drivers.
     */
    private function forceSafeDriversUntilInstalled(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $installed = file_exists(storage_path('installed.lock'))
            || (bool) config('app.installed', false);

        if ($installed) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);
    }

    public function boot(OAuthProviderRegistry $oauth): void
    {

        // Route every Gate::allows()/@can/$user->can() check through the
        // role-based permission system, so policies stay unnecessary for
        // simple slug-based checks across core and extensions.
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ?: null;
        });

        // Laravel Pulse dashboard (/pulse) — super admins only.
        Gate::define('viewPulse', fn (User $user) => $user->is_admin);

        // Registration limiter: real submits stay strict (5/min), but the
        // Precognition validation pings the form sends while typing get a
        // roomier budget so live validation can't lock users out of signing up.
        RateLimiter::for('register', function (Request $request) {
            return $request->isPrecognitive()
                ? Limit::perMinute(30)->by('precognition:'.$request->ip())
                : Limit::perMinute(5)->by($request->ip());
        });

        // Same split for the public contact form (real submits: 5 per 10 min).
        RateLimiter::for('contact', function (Request $request) {
            return $request->isPrecognitive()
                ? Limit::perMinute(30)->by('precognition:'.$request->ip())
                : Limit::perMinutes(10, 5)->by($request->ip());
        });

        // Authenticated account forms (profile, password).
        RateLimiter::for('account-forms', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return $request->isPrecognitive()
                ? Limit::perMinute(30)->by('precognition:'.$key)
                : Limit::perMinute(10)->by($key);
        });

        // Core OAuth providers. Credentials live in admin settings, not .env —
        // SocialAuthController injects them into config() at request time.
        Event::listen(SocialiteWasCalled::class, [DiscordExtendSocialite::class, 'handle']);
        Event::listen(SocialiteWasCalled::class, [SteamExtendSocialite::class, 'handle']);

        // Broadcast every database notification over Reverb the moment it's
        // created — a single choke point so new notification types get live
        // delivery for free, without remembering to fire an event manually
        // at each ->notify() call site.
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->channel !== 'database' || ! $event->response instanceof DatabaseNotification) {
                return;
            }

            if (! $event->notifiable instanceof User) {
                return;
            }

            broadcast(new NotificationCreated($event->notifiable->id, [
                'id' => $event->response->id,
                'type' => $event->response->type,
                'data' => $event->response->data,
                'read' => false,
                'created_at' => $event->response->created_at->diffForHumans(),
            ]));
        });

        $oauth->register(
            id: 'discord',
            name: 'Discord',
            icon: 'MessageSquare',
            scopes: ['identify', 'email'],
            redirectRoute: 'oauth.discord.redirect',
            callbackRoute: 'oauth.discord.callback',
        );

        $oauth->register(
            id: 'steam',
            name: 'Steam',
            icon: 'Gamepad2',
            redirectRoute: 'oauth.steam.redirect',
            callbackRoute: 'oauth.steam.callback',
        );

        $oauth->register(
            id: 'google',
            name: 'Google',
            icon: 'Mail',
            scopes: ['openid', 'profile', 'email'],
            redirectRoute: 'oauth.google.redirect',
            callbackRoute: 'oauth.google.callback',
        );

        // Branded, fully translatable auth mail copy. The recipient's own
        // locale is applied so emails arrive in the user's language.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $locale = $notifiable->locale ?? app()->getLocale();
            $name = $notifiable->name ?? '';
            $app = config('app.name');

            return (new MailMessage)
                ->subject(__('auth.mail_reset_subject', ['app' => $app], $locale))
                ->greeting(__('auth.mail_reset_greeting', ['name' => $name], $locale))
                ->line(__('auth.mail_reset_line1', [], $locale))
                ->action(__('auth.mail_reset_action', [], $locale), $url)
                ->line(__('auth.mail_reset_line2', [], $locale));
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            $locale = $notifiable->locale ?? app()->getLocale();
            $name = $notifiable->name ?? '';
            $app = config('app.name');

            return (new MailMessage)
                ->subject(__('auth.mail_verify_subject', ['app' => $app], $locale))
                ->greeting(__('auth.mail_verify_greeting', ['name' => $name], $locale))
                ->line(__('auth.mail_verify_line1', [], $locale))
                ->action(__('auth.mail_verify_action', [], $locale), $url)
                ->line(__('auth.mail_verify_line2', [], $locale));
        });
    }
}
