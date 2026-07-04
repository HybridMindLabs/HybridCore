<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    private const CACHE_KEY = 'hybridcore.settings';

    private const DEFAULTS = [
        'app_name' => null, // falls back to config('app.name')
        'app_url' => null, // falls back to config('app.url')
        'default_locale' => 'en',
        'timezone' => 'UTC',
        'maintenance_mode' => '0',
        'active_theme' => 'hybridcore/default',
    ];

    /**
     * Keys whose values are encrypted at rest (OAuth client secrets, API
     * keys, etc). Decrypted transparently by get(), encrypted by set().
     */
    private const ENCRYPTED_KEYS = [
        'oauth_discord_client_secret',
        'oauth_steam_client_secret',
        'oauth_google_client_secret',
        'captcha_turnstile_secret_key',
        'captcha_hcaptcha_secret_key',
        'captcha_recaptcha_v2_secret_key',
        'captcha_recaptcha_v3_secret_key',
        'mail_password',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember('settings.'.$key, 300, function () use ($key, $default) {
            $all = $this->all();

            if (array_key_exists($key, $all)) {
                $value = $all[$key];

                if ($value !== null && in_array($key, self::ENCRYPTED_KEYS, true)) {
                    return $this->decrypt($value);
                }

                return $value;
            }

            return $default ?? (self::DEFAULTS[$key] ?? null);
        });
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::all()->pluck('value', 'key')->toArray();
        });
    }

    public function set(string $key, mixed $value): void
    {
        Cache::forget('settings.'.$key);
        $this->setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * Blank values for encrypted keys are skipped — admins leave the field
     * empty to keep the currently stored secret instead of clearing it.
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::ENCRYPTED_KEYS, true)) {
                if ($value === null || $value === '') {
                    continue;
                }

                $value = Crypt::encryptString((string) $value);
            }

            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function appName(): string
    {
        return $this->get('app_name') ?? config('app.name', 'HybridCore');
    }

    private function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
