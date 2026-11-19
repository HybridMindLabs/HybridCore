<?php

namespace App\Support;

use League\CommonMark\CommonMarkConverter;

/**
 * Markdown from admins and page blocks, rendered with raw HTML stripped and
 * unsafe links (javascript:, data:) removed.
 */
final class SafeMarkdown
{
    private static ?CommonMarkConverter $converter = null;

    public static function toHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        self::$converter ??= new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return (string) self::$converter->convert($markdown);
    }
}
