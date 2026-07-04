<?php

namespace App\Http\Middleware;

use App\Services\Localization\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale: URL prefix (public SEO routes) → user
 * preference → session → default setting. Only supported locales are ever
 * applied; everything else falls back.
 *
 * The "locale" route parameter only exists on the public {locale}-prefixed
 * routes registered in routes/web.php (e.g. /en/servers) — admin, account
 * and every other route never has it, so they're unaffected.
 */
class SetLocale
{
    public function __construct(private readonly LocaleService $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale')
            ?? $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $this->locales->defaultLocale();

        if (! is_string($locale) || ! $this->locales->isSupported($locale)) {
            $locale = $this->locales->defaultLocale();
        }

        app()->setLocale($locale);
        app()->setFallbackLocale($this->locales->fallbackLocale());

        return $next($request);
    }
}
