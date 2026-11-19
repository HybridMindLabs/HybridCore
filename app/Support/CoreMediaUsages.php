<?php

namespace App\Support;

use App\Models\EmailTemplate;
use App\Models\LegalPage;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\ThemeSetting;
use App\Services\Extensions\Registries\MediaUsageRegistry;

/**
 * Core places that can show a library image. Extensions add theirs through
 * ExtensionRegistry::mediaUsages().
 */
final class CoreMediaUsages
{
    public static function register(MediaUsageRegistry $usages): void
    {
        $usages->register('Page', Page::class, ['body', 'blocks', 'seo_og_image'],
            fn (Page $p) => $p->title, fn (Page $p) => route('admin.pages.edit', $p));

        $usages->register('News', NewsArticle::class, ['body', 'featured_image', 'og_image'],
            fn (NewsArticle $a) => $a->title, fn (NewsArticle $a) => route('admin.news.articles.edit', $a));

        $usages->register('Rule', Rule::class, ['content'],
            fn (Rule $r) => $r->title, fn (Rule $r) => route('admin.rules.edit', $r->slug));

        $usages->register('Legal page', LegalPage::class, ['content'],
            fn (LegalPage $l) => $l->title, fn (LegalPage $l) => route('admin.legal.edit', $l->slug));

        $usages->register('Email template', EmailTemplate::class, ['body_html'],
            fn (EmailTemplate $t) => $t->name, fn (EmailTemplate $t) => route('admin.email.templates.edit', $t));

        $usages->register('Setting', Setting::class, ['value'],
            fn (Setting $s) => $s->key, fn () => route('admin.settings.index'));

        $usages->register('Theme setting', ThemeSetting::class, ['value'],
            fn (ThemeSetting $s) => $s->key, fn (ThemeSetting $s) => route('admin.themes.show', $s->theme_id));
    }
}
