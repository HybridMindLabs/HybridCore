<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LegalPage;
use App\Services\SettingsService;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use League\CommonMark\CommonMarkConverter;

class LegalController extends Controller
{
    private CommonMarkConverter $markdown;

    public function __construct(private readonly SettingsService $settings)
    {
        // Same settings as the Pages and News controllers.
        $this->markdown = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function show(string $slug): InertiaResponse
    {
        $page = LegalPage::where('slug', $slug)->firstOrFail();

        // Rendered here rather than in the browser. These are the pages that
        // most need to be readable without JavaScript, and doing it server side
        // also keeps the markdown parser out of the public bundle.
        ['html' => $html, 'toc' => $toc] = $this->render($page->content ?? '');

        return Inertia::render('Web/Legal', [
            'slug' => $page->slug,
            'title' => $page->title,
            'subtitle' => $page->subtitle ?? $this->settings->get('seo_meta_description', ''),
            'content' => $html,
            'toc' => $toc,
            // ISO, so the page can format it in the reader's language. It was
            // rendered through a hardcoded en-GB locale.
            'updated_at' => ($page->content_updated_at ?? now())->toIso8601String(),
            'allPages' => LegalPage::orderBy('sort_order')->orderBy('id')
                ->get(['slug', 'title'])
                ->toArray(),
            'canonical' => Seo::canonical('/legal/'.$page->slug),
        ]);
    }

    /**
     * Convert markdown and give every heading a stable, unique id.
     *
     * @return array{html: string, toc: list<array{id: string, text: string, level: int}>}
     */
    private function render(string $markdown): array
    {
        $html = $this->markdown->convert($markdown)->getContent();

        if (trim($html) === '') {
            return ['html' => '', 'toc' => []];
        }

        // DOMDocument assumes Latin-1, so the markup is made ASCII-safe first
        // and decoded again on the way out. A charset <meta> would be the
        // obvious alternative, but under LIBXML_HTML_NOIMPLIED libxml treats it
        // as head content and drops everything that follows it.
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<div id="hc-legal-root">'.$encoded.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toc = [];
        $used = [];

        // XPath rather than getElementsByTagName so the headings come back in
        // document order instead of grouped by tag.
        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('//h2|//h3') ?: [] as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $text = trim($node->textContent);
            if ($text === '') {
                continue;
            }

            $id = $this->uniqueSlug($text, $used);
            $node->setAttribute('id', $id);

            $toc[] = ['id' => $id, 'text' => $text, 'level' => (int) substr($node->tagName, 1)];
        }

        return ['html' => $this->innerHtml($dom), 'toc' => $toc];
    }

    /**
     * The page built these in JavaScript with /[^\w\s-]/, and \w is ASCII-only
     * there: every Cyrillic heading collapsed to the same id "-", so the whole
     * contents list pointed at the first section.
     *
     * @param  array<string, true>  $used
     */
    private function uniqueSlug(string $text, array &$used): string
    {
        $slug = mb_strtolower($text);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $slug) ?? '';
        $slug = trim(preg_replace('/[\s-]+/u', '-', $slug) ?? '', '-');

        if ($slug === '') {
            $slug = 'section';
        }

        $candidate = $slug;
        $n = 2;
        while (isset($used[$candidate])) {
            $candidate = $slug.'-'.$n++;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    /** Contents of the wrapper, without the wrapper itself. */
    private function innerHtml(\DOMDocument $dom): string
    {
        $root = $dom->getElementById('hc-legal-root');

        if (! $root) {
            return '';
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return mb_decode_numericentity($out, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }
}
