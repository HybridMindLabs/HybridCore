<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Mail\TestMailMail;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\SettingsRegistry;
use App\Services\Localization\LocaleService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $activity,
        private readonly SettingsRegistry $extensionSettingsRegistry,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'app_name' => $this->settings->get('app_name', config('app.name')),
                'app_url' => $this->settings->get('app_url', config('app.url')),
                'default_locale' => $this->settings->get('default_locale', 'en'),
                'timezone' => $this->settings->get('timezone', config('app.timezone', 'UTC')),
                'maintenance_mode' => (bool) $this->settings->get('maintenance_mode', '0'),
                'active_theme' => $this->settings->get('active_theme', 'hybridcore/default'),
                'seo_site_title' => $this->settings->get('seo_site_title', ''),
                'seo_meta_description' => $this->settings->get('seo_meta_description', ''),
                'seo_og_image' => $this->settings->get('seo_og_image', ''),
                'social_discord' => $this->settings->get('social_discord', ''),
                'social_steam' => $this->settings->get('social_steam', ''),
                'social_twitter' => $this->settings->get('social_twitter', ''),
                'social_youtube' => $this->settings->get('social_youtube', ''),
                'registration_enabled' => $this->settings->get('registration_enabled', '1') === '1',
                'email_verification_required' => $this->settings->get('email_verification_required', '0') === '1',
                'default_user_role' => $this->settings->get('default_user_role', 'member'),
                'oauth_enabled' => $this->settings->get('oauth_enabled', '0') === '1',
                'oauth_discord_enabled' => $this->settings->get('oauth_discord_enabled', '0') === '1',
                'oauth_discord_client_id' => $this->settings->get('oauth_discord_client_id', ''),
                'oauth_discord_client_secret_set' => filled($this->settings->get('oauth_discord_client_secret')),
                'oauth_steam_enabled' => $this->settings->get('oauth_steam_enabled', '0') === '1',
                'oauth_steam_client_secret_set' => filled($this->settings->get('oauth_steam_client_secret')),
                'oauth_google_enabled' => $this->settings->get('oauth_google_enabled', '0') === '1',
                'oauth_google_client_id' => $this->settings->get('oauth_google_client_id', ''),
                'oauth_google_client_secret_set' => filled($this->settings->get('oauth_google_client_secret')),
                'password_min_length' => (int) ($this->settings->get('password_min_length', '8') ?: 8),
                'password_require_mixed' => $this->settings->get('password_require_mixed', '0') === '1',
                'password_require_numbers' => $this->settings->get('password_require_numbers', '1') === '1',
                'loc_default_locale' => $this->settings->get('localization.default_locale', 'en') ?: 'en',
                'loc_fallback_locale' => $this->settings->get('localization.fallback_locale', 'en') ?: 'en',
                'loc_supported_locales' => $this->settings->get('localization.supported_locales', 'en,bg') ?: 'en,bg',
                'loc_public_switcher' => ($this->settings->get('localization.public_switcher_enabled') ?? '1') === '1',
                'loc_admin_switcher' => ($this->settings->get('localization.admin_switcher_enabled') ?? '1') === '1',
                'avatar_enabled' => ($this->settings->get('avatar_enabled', '1')) === '1',
                'avatar_max_kb' => (int) ($this->settings->get('avatar_max_kb', '2048') ?: 2048),
                'banner_enabled' => ($this->settings->get('banner_enabled', '1')) === '1',
                'banner_max_kb' => (int) ($this->settings->get('banner_max_kb', '4096') ?: 4096),
                'username_change_cooldown_days' => (int) ($this->settings->get('username_change_cooldown_days', '30') ?: 30),
                'dm_enabled' => ($this->settings->get('dm_enabled', '1')) === '1',
                'dm_daily_limit' => (int) ($this->settings->get('dm_daily_limit', '100') ?: 100),
                'dm_max_length' => (int) ($this->settings->get('dm_max_length', '2000') ?: 2000),
                'notif_retention_days' => (int) ($this->settings->get('notif_retention_days', '90') ?: 90),
                // Contact
                'contact_recipient_email' => $this->settings->get('contact_recipient_email', ''),
                // Captcha
                'captcha_provider' => $this->settings->get('captcha_provider', 'none'),
                'captcha_turnstile_site_key' => $this->settings->get('captcha_turnstile_site_key', ''),
                'captcha_turnstile_secret_key_set' => filled($this->settings->get('captcha_turnstile_secret_key')),
                'captcha_hcaptcha_site_key' => $this->settings->get('captcha_hcaptcha_site_key', ''),
                'captcha_hcaptcha_secret_key_set' => filled($this->settings->get('captcha_hcaptcha_secret_key')),
                'captcha_recaptcha_v2_site_key' => $this->settings->get('captcha_recaptcha_v2_site_key', ''),
                'captcha_recaptcha_v2_secret_key_set' => filled($this->settings->get('captcha_recaptcha_v2_secret_key')),
                'captcha_recaptcha_v3_site_key' => $this->settings->get('captcha_recaptcha_v3_site_key', ''),
                'captcha_recaptcha_v3_secret_key_set' => filled($this->settings->get('captcha_recaptcha_v3_secret_key')),
            ],
            'localeCatalog' => app(LocaleService::class)->catalog(),
            'locales' => [
                'en' => 'English',
                'bg' => 'Bulgarian',
                'de' => 'German',
                'fr' => 'French',
                'pl' => 'Polish',
            ],
            'timezones' => timezone_identifiers_list(),
            'extensionSettings' => $this->extensionSettingsRegistry->compose(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->settings->setMany([
            'app_name' => $data['app_name'],
            'app_url' => $data['app_url'],
            'default_locale' => $data['default_locale'],
            'timezone' => $data['timezone'],
            'maintenance_mode' => $data['maintenance_mode'] ? '1' : '0',
            'active_theme' => $data['active_theme'] ?? 'hybridcore/default',
            'seo_site_title' => $data['seo_site_title'] ?? '',
            'seo_meta_description' => $data['seo_meta_description'] ?? '',
            'seo_og_image' => $data['seo_og_image'] ?? '',
            'social_discord' => $data['social_discord'] ?? '',
            'social_steam' => $data['social_steam'] ?? '',
            'social_twitter' => $data['social_twitter'] ?? '',
            'social_youtube' => $data['social_youtube'] ?? '',
            'registration_enabled' => ($data['registration_enabled'] ?? false) ? '1' : '0',
            'email_verification_required' => ($data['email_verification_required'] ?? false) ? '1' : '0',
            'default_user_role' => $data['default_user_role'] ?? 'member',
            'oauth_enabled' => ($data['oauth_enabled'] ?? false) ? '1' : '0',
            'oauth_discord_enabled' => ($data['oauth_discord_enabled'] ?? false) ? '1' : '0',
            'oauth_discord_client_id' => $data['oauth_discord_client_id'] ?? '',
            'oauth_discord_client_secret' => $data['oauth_discord_client_secret'] ?? '',
            'oauth_steam_enabled' => ($data['oauth_steam_enabled'] ?? false) ? '1' : '0',
            'oauth_steam_client_secret' => $data['oauth_steam_client_secret'] ?? '',
            'oauth_google_enabled' => ($data['oauth_google_enabled'] ?? false) ? '1' : '0',
            'oauth_google_client_id' => $data['oauth_google_client_id'] ?? '',
            'oauth_google_client_secret' => $data['oauth_google_client_secret'] ?? '',
            'password_min_length' => (string) ($data['password_min_length'] ?? 8),
            'password_require_mixed' => ($data['password_require_mixed'] ?? false) ? '1' : '0',
            'password_require_numbers' => ($data['password_require_numbers'] ?? true) ? '1' : '0',
            'localization.default_locale' => $data['loc_default_locale'] ?? 'en',
            'localization.fallback_locale' => $data['loc_fallback_locale'] ?? 'en',
            'localization.supported_locales' => $data['loc_supported_locales'] ?? 'en,bg',
            'localization.public_switcher_enabled' => ($data['loc_public_switcher'] ?? true) ? '1' : '0',
            'localization.admin_switcher_enabled' => ($data['loc_admin_switcher'] ?? true) ? '1' : '0',
            'avatar_enabled' => ($data['avatar_enabled'] ?? true) ? '1' : '0',
            'avatar_max_kb' => (string) ($data['avatar_max_kb'] ?? 2048),
            'banner_enabled' => ($data['banner_enabled'] ?? true) ? '1' : '0',
            'banner_max_kb' => (string) ($data['banner_max_kb'] ?? 4096),
            'username_change_cooldown_days' => (string) ($data['username_change_cooldown_days'] ?? 30),
            'dm_enabled' => ($data['dm_enabled'] ?? true) ? '1' : '0',
            'dm_daily_limit' => (string) ($data['dm_daily_limit'] ?? 100),
            'dm_max_length' => (string) ($data['dm_max_length'] ?? 2000),
            'notif_retention_days' => (string) ($data['notif_retention_days'] ?? 90),
            // Contact
            'contact_recipient_email' => $data['contact_recipient_email'] ?? '',
            // Captcha
            'captcha_provider' => $data['captcha_provider'] ?? 'none',
            'captcha_turnstile_site_key' => $data['captcha_turnstile_site_key'] ?? '',
            'captcha_turnstile_secret_key' => $data['captcha_turnstile_secret_key'] ?? '',
            'captcha_hcaptcha_site_key' => $data['captcha_hcaptcha_site_key'] ?? '',
            'captcha_hcaptcha_secret_key' => $data['captcha_hcaptcha_secret_key'] ?? '',
            'captcha_recaptcha_v2_site_key' => $data['captcha_recaptcha_v2_site_key'] ?? '',
            'captcha_recaptcha_v2_secret_key' => $data['captcha_recaptcha_v2_secret_key'] ?? '',
            'captcha_recaptcha_v3_site_key' => $data['captcha_recaptcha_v3_site_key'] ?? '',
            'captcha_recaptcha_v3_secret_key' => $data['captcha_recaptcha_v3_secret_key'] ?? '',
        ]);

        $this->activity->log('settings.updated', 'Updated platform settings');

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings saved.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $to = $request->input('email');

        try {
            Mail::to($to)->send(new TestMailMail($to));
        } catch (\Throwable $e) {
            return back()->with('error', 'Mail failed: '.$e->getMessage());
        }

        return back()->with('success', 'Test email sent to '.$to);
    }
}
