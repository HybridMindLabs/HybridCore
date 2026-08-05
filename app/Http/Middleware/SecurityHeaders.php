<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce for the few inline scripts (theme bootstrap, Ziggy routes).
        // Must be generated before the view renders so @vite/@routes pick it up.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        // allow-popups, not a bare same-origin: OAuth providers (Discord/Steam/
        // Google) round-trip through a full-page redirect here, not a popup, but
        // a plain same-origin would still break window.opener on any future
        // popup-based flow (password managers, share dialogs) for no gain.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        // Stops another origin from <img>/<script>-loading this site's responses
        // and reading them via a timing/Spectre-class side channel. Nothing here
        // is meant to be embedded cross-origin, so same-origin costs nothing.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Credential-entry pages must never be written to a shared cache — a
        // proxy, a browser's back-forward cache, or a synced profile could hand
        // the next person a filled-in login or password-reset screen.
        if ($this->isSensitivePath($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        // CSP breaks the Vite dev server (HMR injects its own scripts), so
        // it's only applied when serving built assets. The Pulse dashboard
        // (Livewire, admin-only) ships its own inline scripts and is exempt.
        if (! file_exists(public_path('hot')) && ! $request->is('pulse', 'pulse/*', 'horizon', 'horizon/*')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce, $request));
        }

        // HSTS only over HTTPS in production — a cached HSTS header on a
        // local/plain-HTTP setup would lock the browser out of the site.
        if (app()->isProduction() && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /** Login, registration, and password-reset flows — never cache these. */
    private function isSensitivePath(Request $request): bool
    {
        return $request->is(
            'login', 'register', 'forgot-password', 'reset-password', 'reset-password/*',
            'admin/login',
        );
    }

    private function contentSecurityPolicy(string $nonce, Request $request): string
    {
        // External hosts are the captcha providers (Turnstile / hCaptcha /
        // reCAPTCHA); ws:/wss: is the Reverb websocket. Style needs
        // 'unsafe-inline' because Vue :style bindings are inline styles.
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com https://js.hcaptcha.com https://www.google.com https://www.gstatic.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            // Every captcha provider that gets script-src also needs connect-src:
            // each one phones home after loading, and reCAPTCHA in particular
            // fails closed when its XHR to google.com is refused — the widget
            // renders and the form then cannot be submitted.
            "connect-src 'self' ws: wss: https://challenges.cloudflare.com https://js.hcaptcha.com https://hcaptcha.com https://www.google.com https://www.gstatic.com",
            'frame-src https://challenges.cloudflare.com https://newassets.hcaptcha.com https://js.hcaptcha.com https://www.google.com https://recaptcha.google.com',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        // Rewrites any stray http:// sub-resource URL to https:// in the
        // browser rather than either silently mixed-content-blocking it or
        // serving it in the clear — same threshold as HSTS above.
        if (app()->isProduction() && $request->secure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
