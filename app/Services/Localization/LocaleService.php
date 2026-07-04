<?php

namespace App\Services\Localization;

use App\Services\SettingsService;

/**
 * Locale configuration resolved from settings with safe defaults.
 * The locale catalog below is the set the core knows how to label;
 * which of them are ACTIVE is controlled by settings — extensible
 * without code changes for any locale added to the catalog.
 */
class LocaleService
{
    /**
     * Known locale metadata. Adding a language here (or via register())
     * makes it selectable in the admin Localization settings.
     *
     * @var array<string, array{name: string, native_name: string, flag: string, direction: string}>
     */
    private array $catalog = [
        'en' => ['name' => 'English',   'native_name' => 'English',   'flag' => 'EN', 'direction' => 'ltr'],
        'bg' => ['name' => 'Bulgarian', 'native_name' => 'Български', 'flag' => 'BG', 'direction' => 'ltr'],
        'de' => ['name' => 'German',    'native_name' => 'Deutsch',   'flag' => 'DE', 'direction' => 'ltr'],
        'fr' => ['name' => 'French',    'native_name' => 'Français',  'flag' => 'FR', 'direction' => 'ltr'],
        'pl' => ['name' => 'Polish',    'native_name' => 'Polski',    'flag' => 'PL', 'direction' => 'ltr'],
        'ru' => ['name' => 'Russian',   'native_name' => 'Русский',   'flag' => 'RU', 'direction' => 'ltr'],
        'tr' => ['name' => 'Turkish',   'native_name' => 'Türkçe',    'flag' => 'TR', 'direction' => 'ltr'],
        'ar' => ['name' => 'Arabic',    'native_name' => 'العربية',   'flag' => 'AR', 'direction' => 'rtl'],
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /** Extensions can add locales to the catalog. */
    public function register(string $code, string $name, string $nativeName, string $direction = 'ltr', ?string $flag = null): void
    {
        $this->catalog[$code] = [
            'name' => $name,
            'native_name' => $nativeName,
            'flag' => $flag ?? strtoupper($code),
            'direction' => $direction,
        ];
    }

    public function defaultLocale(): string
    {
        $locale = (string) ($this->settings->get('localization.default_locale')
            ?: $this->settings->get('default_locale', 'en'));

        return $this->isSupported($locale) ? $locale : 'en';
    }

    public function fallbackLocale(): string
    {
        $locale = (string) ($this->settings->get('localization.fallback_locale') ?: 'en');

        return isset($this->catalog[$locale]) ? $locale : 'en';
    }

    /** @return array<int, string> Active locale codes. */
    public function supportedCodes(): array
    {
        $raw = (string) ($this->settings->get('localization.supported_locales') ?: 'en,bg');

        $codes = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $code) => isset($this->catalog[$code]),
        ));

        return $codes === [] ? ['en'] : $codes;
    }

    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedCodes(), true);
    }

    /**
     * Frontend-ready supported locale list.
     *
     * @return array<int, array{code: string, name: string, native_name: string, flag: string, direction: string}>
     */
    public function supportedLocales(): array
    {
        return array_map(
            fn (string $code) => array_merge(['code' => $code], $this->catalog[$code]),
            $this->supportedCodes(),
        );
    }

    public function direction(string $locale): string
    {
        return $this->catalog[$locale]['direction'] ?? 'ltr';
    }

    public function publicSwitcherEnabled(): bool
    {
        return ($this->settings->get('localization.public_switcher_enabled') ?? '1') === '1';
    }

    public function adminSwitcherEnabled(): bool
    {
        return ($this->settings->get('localization.admin_switcher_enabled') ?? '1') === '1';
    }

    /** @return array<string, array{name: string, native_name: string, flag: string, direction: string}> */
    public function catalog(): array
    {
        return $this->catalog;
    }
}
