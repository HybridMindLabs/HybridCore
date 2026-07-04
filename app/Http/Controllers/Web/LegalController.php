<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Services\SettingsService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LegalController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function show(string $slug): InertiaResponse
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        return Inertia::render('Web/Legal', [
            'slug' => $page->slug,
            'title' => $page->title,
            'subtitle' => $page->subtitle ?? $this->settings->get('seo_meta_description', ''),
            'content' => $page->content ?? '',
            'updated_at' => $page->content_updated_at
                ? $page->content_updated_at->format('Y-m-d')
                : now()->format('Y-m-d'),
            'allPages' => LegalPage::orderBy('sort_order')->orderBy('id')
                ->get(['slug', 'title'])
                ->toArray(),
        ]);
    }
}
