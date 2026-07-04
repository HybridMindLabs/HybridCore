<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'default_locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'timezone'],
            'maintenance_mode' => ['boolean'],
            'active_theme' => ['nullable', 'string', 'max:255'],
            'seo_site_title' => ['nullable', 'string', 'max:255'],
            'seo_meta_description' => ['nullable', 'string', 'max:500'],
            'seo_og_image' => ['nullable', 'string', 'max:255'],
            'social_discord' => ['nullable', 'url', 'max:255'],
            'social_steam' => ['nullable', 'url', 'max:255'],
            'social_twitter' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'registration_enabled' => ['boolean'],
            'email_verification_required' => ['boolean'],
            'default_user_role' => ['nullable', 'string', 'max:100'],
            'oauth_enabled' => ['boolean'],
            'oauth_discord_enabled' => ['boolean'],
            'oauth_discord_client_id' => ['nullable', 'string', 'max:255'],
            'oauth_discord_client_secret' => ['nullable', 'string', 'max:255'],
            'oauth_steam_enabled' => ['boolean'],
            'oauth_steam_client_secret' => ['nullable', 'string', 'max:255'],
            'oauth_google_enabled' => ['boolean'],
            'oauth_google_client_id' => ['nullable', 'string', 'max:255'],
            'oauth_google_client_secret' => ['nullable', 'string', 'max:255'],
            'password_min_length' => ['nullable', 'integer', 'min:8', 'max:128'],
            'password_require_mixed' => ['boolean'],
            'password_require_numbers' => ['boolean'],
            'loc_default_locale' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(-[A-Za-z]{2,4})?$/'],
            'loc_fallback_locale' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{2}(-[A-Za-z]{2,4})?$/'],
            'loc_supported_locales' => ['nullable', 'string', 'max:255', 'regex:/^[a-z]{2}(\s*,\s*[a-z]{2})*$/'],
            'loc_public_switcher' => ['boolean'],
            'loc_admin_switcher' => ['boolean'],
            'avatar_enabled' => ['boolean'],
            'avatar_max_kb' => ['nullable', 'integer', 'min:128', 'max:10240'],
            'banner_enabled' => ['boolean'],
            'banner_max_kb' => ['nullable', 'integer', 'min:512', 'max:20480'],
            'username_change_cooldown_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'dm_enabled' => ['boolean'],
            'dm_daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'dm_max_length' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'notif_retention_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            // Contact
            'contact_recipient_email' => ['nullable', 'email', 'max:255'],
            // Captcha
            'captcha_provider' => ['nullable', 'string', 'in:none,turnstile,hcaptcha,recaptcha_v2,recaptcha_v3'],
            'captcha_turnstile_site_key' => ['nullable', 'string', 'max:255'],
            'captcha_turnstile_secret_key' => ['nullable', 'string', 'max:255'],
            'captcha_hcaptcha_site_key' => ['nullable', 'string', 'max:255'],
            'captcha_hcaptcha_secret_key' => ['nullable', 'string', 'max:255'],
            'captcha_recaptcha_v2_site_key' => ['nullable', 'string', 'max:255'],
            'captcha_recaptcha_v2_secret_key' => ['nullable', 'string', 'max:255'],
            'captcha_recaptcha_v3_site_key' => ['nullable', 'string', 'max:255'],
            'captcha_recaptcha_v3_secret_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
