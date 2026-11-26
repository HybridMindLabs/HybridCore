<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans staff-written HTML (pages and news in the "html" format) before it is
 * shown to visitors. Without it any account allowed to write content could
 * store a script that runs for every reader — admins included.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(string $html): string
    {
        return self::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();

        // Building the definition is the slow part; keep it on disk between requests.
        $cache = storage_path('framework/cache/htmlpurifier');
        if (is_dir($cache) || @mkdir($cache, 0755, true)) {
            $config->set('Cache.SerializerPath', $cache);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        // Links that open a new tab must not hand the opener to the target site.
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.TargetNoopener', true);

        return self::$purifier = new HTMLPurifier($config);
    }
}
