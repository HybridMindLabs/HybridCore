import type { InertiaForm } from '@inertiajs/vue3';

export interface PlatformSettings {
    app_name: string;
    app_url: string;
    default_locale: string;
    timezone: string;
    maintenance_mode: boolean;
    active_theme: string;
    active_theme_name: string | null;
    seo_site_title: string;
    seo_meta_description: string;
    seo_og_image: string;
    social_discord: string;
    social_steam: string;
    social_twitter: string;
    social_youtube: string;
    registration_enabled: boolean;
    email_verification_required: boolean;
    default_user_role: string;
    oauth_enabled: boolean;
    oauth_discord_enabled: boolean;
    oauth_discord_client_id: string;
    oauth_discord_client_secret_set: boolean;
    oauth_steam_enabled: boolean;
    oauth_steam_client_secret_set: boolean;
    oauth_google_enabled: boolean;
    oauth_google_client_id: string;
    oauth_google_client_secret_set: boolean;
    password_min_length: number;
    password_require_mixed: boolean;
    password_require_numbers: boolean;
    require_2fa_for_admins: boolean;
    require_2fa_grace_days: number;
    loc_default_locale: string;
    loc_fallback_locale: string;
    loc_supported_locales: string;
    loc_public_switcher: boolean;
    loc_admin_switcher: boolean;
    avatar_enabled: boolean;
    avatar_max_kb: number;
    banner_enabled: boolean;
    banner_max_kb: number;
    username_change_cooldown_days: number;
    dm_enabled: boolean;
    dm_daily_limit: number;
    dm_max_length: number;
    notif_retention_days: number;
    contact_recipient_email: string;
    captcha_provider: string;
    captcha_turnstile_site_key: string;
    captcha_turnstile_secret_key_set: boolean;
    captcha_hcaptcha_site_key: string;
    captcha_hcaptcha_secret_key_set: boolean;
    captcha_recaptcha_v2_site_key: string;
    captcha_recaptcha_v2_secret_key_set: boolean;
    captcha_recaptcha_v3_site_key: string;
    captcha_recaptcha_v3_secret_key_set: boolean;
}

/**
 * The editable form state. Secrets always start empty: the server only says
 * whether one is set, and an empty field on save means "keep the current one".
 */
export function settingsFormData(s: PlatformSettings) {
    return {
        app_name:                      s.app_name,
        app_url:                       s.app_url,
        default_locale:                s.default_locale,
        timezone:                      s.timezone,
        maintenance_mode:              s.maintenance_mode,
        active_theme:                  s.active_theme,
        seo_site_title:                s.seo_site_title ?? '',
        seo_meta_description:          s.seo_meta_description ?? '',
        seo_og_image:                  s.seo_og_image ?? '',
        social_discord:                s.social_discord ?? '',
        social_steam:                  s.social_steam ?? '',
        social_twitter:                s.social_twitter ?? '',
        social_youtube:                s.social_youtube ?? '',
        registration_enabled:          s.registration_enabled ?? true,
        email_verification_required:   s.email_verification_required ?? false,
        default_user_role:             s.default_user_role ?? 'member',
        oauth_enabled:                 s.oauth_enabled ?? false,
        oauth_discord_enabled:         s.oauth_discord_enabled ?? false,
        oauth_discord_client_id:       s.oauth_discord_client_id ?? '',
        oauth_discord_client_secret:   '',
        oauth_steam_enabled:           s.oauth_steam_enabled ?? false,
        oauth_steam_client_secret:     '',
        oauth_google_enabled:          s.oauth_google_enabled ?? false,
        oauth_google_client_id:        s.oauth_google_client_id ?? '',
        oauth_google_client_secret:    '',
        password_min_length:           s.password_min_length ?? 8,
        password_require_mixed:        s.password_require_mixed ?? false,
        password_require_numbers:      s.password_require_numbers ?? true,
        require_2fa_for_admins:        s.require_2fa_for_admins ?? true,
        require_2fa_grace_days:        s.require_2fa_grace_days ?? 3,
        loc_default_locale:            s.loc_default_locale ?? 'en',
        loc_fallback_locale:           s.loc_fallback_locale ?? 'en',
        loc_supported_locales:         s.loc_supported_locales ?? 'en,bg',
        loc_public_switcher:           s.loc_public_switcher ?? true,
        loc_admin_switcher:            s.loc_admin_switcher ?? true,
        avatar_enabled:                s.avatar_enabled ?? true,
        avatar_max_kb:                 s.avatar_max_kb ?? 2048,
        banner_enabled:                s.banner_enabled ?? true,
        banner_max_kb:                 s.banner_max_kb ?? 4096,
        username_change_cooldown_days: s.username_change_cooldown_days ?? 30,
        dm_enabled:                    s.dm_enabled ?? true,
        dm_daily_limit:                s.dm_daily_limit ?? 100,
        dm_max_length:                 s.dm_max_length ?? 2000,
        notif_retention_days:          s.notif_retention_days ?? 90,
        contact_recipient_email:       s.contact_recipient_email ?? '',
        captcha_provider:              s.captcha_provider ?? 'none',
        captcha_turnstile_site_key:    s.captcha_turnstile_site_key ?? '',
        captcha_turnstile_secret_key:  '',
        captcha_hcaptcha_site_key:     s.captcha_hcaptcha_site_key ?? '',
        captcha_hcaptcha_secret_key:   '',
        captcha_recaptcha_v2_site_key: s.captcha_recaptcha_v2_site_key ?? '',
        captcha_recaptcha_v2_secret_key: '',
        captcha_recaptcha_v3_site_key: s.captcha_recaptcha_v3_site_key ?? '',
        captcha_recaptcha_v3_secret_key: '',
    };
}

export type SettingsForm = InertiaForm<ReturnType<typeof settingsFormData>>;

export const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 placeholder:text-zinc-600';

export const numberClass = 'w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 disabled:opacity-40';
