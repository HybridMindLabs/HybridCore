<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\LegalPage;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\Page;
use App\Models\Rule;
use App\Models\Server;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        // Fully generated from the database, so it never goes stale by
        // more than an hour; cached because crawlers hit it frequently.
        $xml = Cache::remember('seo.sitemap', 3600, fn () => $this->buildSitemap());

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function buildSitemap(): string
    {
        $urls = [['loc' => Seo::canonical('/'), 'priority' => '1.0', 'alternates' => Seo::alternates('/')]];

        $urls[] = ['loc' => Seo::canonical('/members'), 'priority' => '0.6'];
        $urls[] = ['loc' => Seo::canonical('/contact'), 'priority' => '0.5'];

        if (Schema::hasTable('servers')) {
            $urls[] = ['loc' => Seo::canonical('/servers'), 'priority' => '0.8', 'alternates' => Seo::alternates('/servers')];

            foreach (Game::active()->get(['slug']) as $game) {
                $urls[] = ['loc' => Seo::canonical('/servers/'.$game->slug), 'priority' => '0.7'];
            }

            foreach (Server::active()->with('game')->get() as $server) {
                if (! $server->game) {
                    continue;
                }
                $urls[] = [
                    'loc' => Seo::canonical("/servers/{$server->game->slug}/{$server->ip}/{$server->port}"),
                    'priority' => '0.6',
                ];
            }
        }

        if (Schema::hasTable('rules')) {
            $urls[] = ['loc' => Seo::canonical('/rules'), 'priority' => '0.7'];

            foreach (Rule::published()->get(['slug', 'updated_at']) as $rule) {
                $urls[] = [
                    'loc' => Seo::canonical('/rules/'.$rule->slug),
                    'priority' => '0.6',
                    'lastmod' => $rule->updated_at?->toAtomString(),
                ];
            }
        }

        if (Schema::hasTable('legal_pages')) {
            foreach (LegalPage::orderBy('sort_order')->get(['slug', 'updated_at']) as $legal) {
                $urls[] = [
                    'loc' => Seo::canonical('/legal/'.$legal->slug),
                    'priority' => '0.4',
                    'lastmod' => $legal->updated_at?->toAtomString(),
                ];
            }
        }

        if (Schema::hasTable('news_articles')) {
            $urls[] = ['loc' => Seo::canonical('/news'), 'priority' => '0.9'];

            foreach (NewsCategory::all() as $cat) {
                $urls[] = ['loc' => Seo::canonical('/news/category/'.$cat->slug), 'priority' => '0.7'];
            }

            foreach (NewsArticle::published()->orderByDesc('published_at')->get() as $article) {
                $urls[] = [
                    'loc' => Seo::canonical('/news/'.$article->slug),
                    'priority' => '0.8',
                    'lastmod' => $article->updated_at?->toAtomString(),
                ];
            }
        }

        if (Schema::hasTable('pages')) {
            foreach (Page::published()->get() as $page) {
                $urls[] = [
                    'loc' => Seo::canonical('/'.$page->slug),
                    'priority' => '0.6',
                    'lastmod' => $page->updated_at?->toAtomString(),
                    'alternates' => Seo::alternates('/'.$page->slug),
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e($url['loc'])."</loc>\n";
            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod']."</lastmod>\n";
            }
            $xml .= '    <priority>'.$url['priority']."</priority>\n";
            foreach ($url['alternates'] ?? [] as $locale => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.e($locale).'" href="'.e($href).'" />'."\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /install',
            '',
            'Sitemap: '.Seo::canonical('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
