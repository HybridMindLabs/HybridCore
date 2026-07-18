<?php

namespace App\Support;

use League\CommonMark\CommonMarkConverter;

/**
 * Markdown to HTML, with anchored headings and a table of contents.
 *
 * The legal and rule pages used to parse markdown in the browser, which meant
 * their text only existed once JavaScript had run, and built heading ids with
 * an ASCII-only pattern that collapsed every Cyrillic heading to the same id.
 */
class MarkdownRenderer
{
    /** Marks the wrapper the parser needs but the output must not keep. */
    private const ROOT_ID = 'hc-md-root';

    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public function toHtml(?string $markdown): string
    {
        return $this->converter->convert((string) $markdown)->getContent();
    }

    /**
     * @return array{html: string, toc: list<array{id: string, text: string, level: int}>}
     */
    public function render(?string $markdown): array
    {
        $html = $this->toHtml($markdown);

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
            '<div id="'.self::ROOT_ID.'">'.$encoded.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $toc = [];
        $used = [];

        // XPath rather than getElementsByTagName, which groups by tag instead
        // of returning the headings in document order.
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

    /** Words of prose, ignoring the markup around them. */
    public function wordCount(string $html): int
    {
        return str_word_count(strip_tags($html));
    }

    /**
     * \p{L} rather than \w, so Cyrillic, Greek and the rest survive instead of
     * being stripped down to an empty slug.
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

    private function innerHtml(\DOMDocument $dom): string
    {
        $root = $dom->getElementById(self::ROOT_ID);

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
