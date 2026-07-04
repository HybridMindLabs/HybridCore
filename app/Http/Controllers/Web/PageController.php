<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\CommonMarkConverter;

class PageController extends Controller
{
    private CommonMarkConverter $markdown;

    public function __construct()
    {
        $this->markdown = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function show(string $slug): Response
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        $body = match ($page->format) {
            'html' => $page->body ? $this->substitutePlaceholders($page->body) : null,
            default => $page->body ? $this->renderMarkdown($page->body) : null,
        };

        return Inertia::render('Web/Page', [
            'page' => [
                'title' => $page->title,
                'body' => $body,
                'layout' => $page->layout ?? 'default',
                'format' => $page->format ?? 'markdown',
            ],
            'seo' => Seo::forPage($page),
        ]);
    }

    private function renderMarkdown(string $content): string
    {
        return (string) $this->markdown->convert($this->substitutePlaceholders($content));
    }

    private function substitutePlaceholders(string $content): string
    {
        return str_replace(
            ['{site_name}', '{contact_email}'],
            [config('app.name', 'HybridCore'), config('app.contact_email', '')],
            $content
        );
    }
}
