<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CaptchaService
{
    public const PROVIDERS = ['none', 'turnstile', 'hcaptcha', 'recaptcha_v2', 'recaptcha_v3'];

    public function __construct(private readonly SettingsService $settings) {}

    public function activeProvider(): string
    {
        return $this->settings->get('captcha_provider', 'none') ?: 'none';
    }

    public function isEnabled(): bool
    {
        return $this->activeProvider() !== 'none';
    }

    public function siteKey(): ?string
    {
        return match ($this->activeProvider()) {
            'turnstile' => $this->settings->get('captcha_turnstile_site_key'),
            'hcaptcha' => $this->settings->get('captcha_hcaptcha_site_key'),
            'recaptcha_v2' => $this->settings->get('captcha_recaptcha_v2_site_key'),
            'recaptcha_v3' => $this->settings->get('captcha_recaptcha_v3_site_key'),
            default => null,
        };
    }

    /** Verify the token from the request. Returns true if disabled or passes. */
    public function verify(Request $request): bool
    {
        $provider = $this->activeProvider();

        if ($provider === 'none') {
            return true;
        }

        $token = $request->input('captcha_token');

        if (! $token) {
            return false;
        }

        return match ($provider) {
            'turnstile' => $this->verifyTurnstile($token, $request->ip()),
            'hcaptcha' => $this->verifyHcaptcha($token, $request->ip()),
            'recaptcha_v2' => $this->verifyRecaptcha($token, $request->ip()),
            'recaptcha_v3' => $this->verifyRecaptchaV3($token, $request->ip()),
            default => true,
        };
    }

    private function verifyTurnstile(string $token, ?string $ip): bool
    {
        $secret = $this->settings->get('captcha_turnstile_secret_key');

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return (bool) ($response->json('success') ?? false);
    }

    private function verifyHcaptcha(string $token, ?string $ip): bool
    {
        $secret = $this->settings->get('captcha_hcaptcha_secret_key');

        $response = Http::asForm()->post('https://hcaptcha.com/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return (bool) ($response->json('success') ?? false);
    }

    private function verifyRecaptcha(string $token, ?string $ip): bool
    {
        $secret = $this->settings->get('captcha_recaptcha_v2_secret_key');

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        return (bool) ($response->json('success') ?? false);
    }

    private function verifyRecaptchaV3(string $token, ?string $ip): bool
    {
        $secret = $this->settings->get('captcha_recaptcha_v3_secret_key');

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        $data = $response->json();
        $score = (float) ($data['score'] ?? 0);

        return (bool) ($data['success'] ?? false) && $score >= 0.5;
    }
}
