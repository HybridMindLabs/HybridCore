<?php

namespace App\Support;

use App\Models\Page;
use App\Services\Localization\LocaleService;
use App\Services\SettingsService;

/**
 * SEO helpers: resolves per-page SEO with global fallbacks and builds
 * canonical URLs.
 */
class Seo
{
    /** @return array{title: string, description: string|null, og_image: string|null, canonical: string} */
    public static function forPage(Page $page): array
    {
        $settings = app(SettingsService::class);

        return [
            'title' => $page->seo_title ?: $page->title.' — '.($settings->get('seo_site_title') ?: $settings->appName()),
            'description' => $page->seo_description ?: $settings->get('seo_meta_description'),
            'og_image' => $page->seo_og_image ?: $settings->get('seo_og_image'),
            'canonical' => self::canonical('/'.$page->slug),
        ];
    }

    public static function canonical(string $path): string
    {
        $base = rtrim((string) (app(SettingsService::class)->get('app_url') ?: config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    /** Locale-prefixed canonical URL for a public page, e.g. /en/servers. */
    public static function localizedCanonical(string $path, string $locale): string
    {
        return self::canonical($locale.'/'.ltrim($path, '/'));
    }

    /**
     * hreflang alternate links for every active locale, suitable for the
     * <xhtml:link> entries inside a sitemap <url> block.
     *
     * @return array<string, string> Locale code => absolute URL
     */
    public static function alternates(string $path): array
    {
        $locales = app(LocaleService::class)->supportedCodes();

        return array_combine($locales, array_map(
            fn (string $locale) => self::localizedCanonical($path, $locale),
            $locales,
        ));
    }
}
