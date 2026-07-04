<?php

/**
 * Vendor patches for PHP 8.4 compatibility.
 * Runs automatically via composer post-autoload-dump.
 */
$patches = [
    // austinb/gameq — implicit nullable deprecated in PHP 8.4
    __DIR__.'/../vendor/austinb/gameq/src/GameQ/Protocol.php' => [
        'public function packetResponse(array $response = null)',
        'public function packetResponse(?array $response = null)',
    ],
];

foreach ($patches as $file => [$search, $replace]) {
    if (! file_exists($file)) {
        continue;
    }
    $content = file_get_contents($file);
    if (str_contains($content, $search)) {
        file_put_contents($file, str_replace($search, $replace, $content));
        echo '  Patched: '.basename(dirname($file)).'/'.basename($file).PHP_EOL;
    }
}
