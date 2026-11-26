<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Gives every h2/h3 in rendered article HTML a stable id and lists them, so the
 * page can show a table of contents and sections can be linked to directly.
 * Done on the server so the anchors are in the first paint and search results.
 */
class HeadingAnchors
{
    /**
     * @return array{html: string, headings: array<int, array{id: string, text: string, level: int}>}
     */
    public static function add(string $html): array
    {
        if (trim($html) === '' || ! preg_match('/<h[23][\s>]/i', $html)) {
            return ['html' => $html, 'headings' => []];
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The wrapper keeps the fragment's encoding and lets us read back just its children.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="hc-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $headings = [];
        $used = [];
        $xpath = new DOMXPath($doc);

        foreach ($xpath->query('//h2 | //h3') ?: [] as $index => $node) {
            /** @var DOMElement $node */
            $text = trim($node->textContent);
            if ($text === '') {
                continue;
            }

            $id = self::slug($text) ?: 'section-'.($index + 1);
            $base = $id;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base.'-'.$n;
            }
            $used[$id] = true;

            $node->setAttribute('id', $id);
            $headings[] = ['id' => $id, 'text' => $text, 'level' => strtolower($node->nodeName) === 'h2' ? 2 : 3];
        }

        $root = $doc->getElementById('hc-root');
        $out = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['html' => $out, 'headings' => $headings];
    }

    /** A readable id from heading text; keeps letters of any script, e.g. Cyrillic. */
    private static function slug(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';

        return mb_substr(trim($slug, '-'), 0, 60);
    }
}
