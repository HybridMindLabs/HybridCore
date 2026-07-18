<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Services\SettingsService;
use App\Support\MarkdownRenderer;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LegalController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function show(string $slug): InertiaResponse
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        // Rendered here rather than in the browser. These are the pages that
        // most need to be readable without JavaScript, and doing it server side
        // also keeps the markdown parser out of the public bundle.
        ['html' => $html, 'toc' => $toc] = $this->markdown->render($page->content);

        return Inertia::render('Web/Legal', [
            'slug' => $page->slug,
            'title' => $page->title,
            'subtitle' => $page->subtitle ?? $this->settings->get('seo_meta_description', ''),
            'content' => $html,
            'toc' => $toc,
            // Figures a reader actually wonders about before starting: how long
            // this is, and how many sections it breaks into.
            'reading_minutes' => max(1, (int) round($this->markdown->wordCount($html) / 200)),
            // ISO, so the page can format it in the reader's language. It was
            // rendered through a hardcoded en-GB locale.
            'updated_at' => ($page->content_updated_at ?? now())->toIso8601String(),
            'allPages' => LegalPage::orderBy('sort_order')->orderBy('id')
                ->get(['slug', 'title'])
                ->toArray(),
            'canonical' => Seo::canonical('/legal/'.$page->slug),
        ]);
    }
}
