<?php

namespace App\Services\Themes;

use DirectoryIterator;
use Throwable;

/**
 * Scans the themes/ directory and reads theme.json manifests.
 *
 * SAFETY: this service reads JSON files only — it never includes or
 * executes any PHP code from the themes directory.
 */
class ThemeDiscoveryService
{
    private const REQUIRED_FIELDS = ['name', 'slug', 'version'];

    /**
     * Discover all valid themes under base_path('themes/').
     *
     * Supports two layouts:
     *   themes/{Name}/theme.json         (flat)
     *   themes/{Vendor}/{Name}/theme.json (vendor/name)
     *
     * @return array<int, array<string, mixed>>
     */
    public function discover(): array
    {
        $basePath = base_path('themes');

        if (! is_dir($basePath)) {
            return [];
        }

        $found = [];

        foreach (new DirectoryIterator($basePath) as $item) {
            if (! $item->isDir() || $item->isDot()) {
                continue;
            }

            // Flat layout: themes/Name/theme.json
            $manifest = $item->getPathname().'/theme.json';
            if (file_exists($manifest)) {
                $parsed = $this->parseManifest($manifest, $item->getFilename());
                if ($parsed !== null) {
                    $found[] = $parsed;
                }

                continue;
            }

            // Vendor/name layout: themes/Vendor/Name/theme.json
            foreach (new DirectoryIterator($item->getPathname()) as $sub) {
                if (! $sub->isDir() || $sub->isDot()) {
                    continue;
                }
                $manifest = $sub->getPathname().'/theme.json';
                if (file_exists($manifest)) {
                    $relPath = $item->getFilename().'/'.$sub->getFilename();
                    $parsed = $this->parseManifest($manifest, $relPath);
                    if ($parsed !== null) {
                        $found[] = $parsed;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @return array<string, mixed>|null Returns null when manifest is invalid or unreadable.
     */
    private function parseManifest(string $manifestPath, string $relPath): ?array
    {
        try {
            $raw = file_get_contents($manifestPath);

            if ($raw === false) {
                return null;
            }

            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            foreach (self::REQUIRED_FIELDS as $field) {
                if (empty($data[$field])) {
                    return null;
                }
            }

            return array_merge($data, ['path' => $relPath]);
        } catch (Throwable) {
            return null;
        }
    }
}
