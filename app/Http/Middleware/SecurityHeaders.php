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

        // CSP breaks the Vite dev server (HMR injects its own scripts), so
        // it's only applied when serving built assets. The Pulse dashboard
        // (Livewire, admin-only) ships its own inline scripts and is exempt.
        if (! file_exists(public_path('hot')) && ! $request->is('pulse', 'pulse/*', 'horizon', 'horizon/*')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        }

        // HSTS only over HTTPS in production — a cached HSTS header on a
        // local/plain-HTTP setup would lock the browser out of the site.
        if (app()->isProduction() && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        // External hosts are the captcha providers (Turnstile / hCaptcha /
        // reCAPTCHA); ws:/wss: is the Reverb websocket. Style needs
        // 'unsafe-inline' because Vue :style bindings are inline styles.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com https://js.hcaptcha.com https://www.google.com https://www.gstatic.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' ws: wss: https://challenges.cloudflare.com",
            'frame-src https://challenges.cloudflare.com https://newassets.hcaptcha.com https://js.hcaptcha.com https://www.google.com https://recaptcha.google.com',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
