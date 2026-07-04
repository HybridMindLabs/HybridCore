<?php

namespace App\Services\Extensions;

use DirectoryIterator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scans the extensions/ directory and reads extension.json manifests.
 *
 * SAFETY: this service reads JSON files only — it never includes or
 * executes any PHP code from the extensions directory.
 */
class ExtensionDiscoveryService
{
    public function __construct(private readonly ExtensionManifestValidator $validator) {}

    /**
     * Discover all valid extensions under base_path('extensions/').
     *
     * Supports two layouts:
     *   extensions/{Name}/extension.json         (flat)
     *   extensions/{Vendor}/{Name}/extension.json (vendor/name)
     *
     * @return array<int, array<string, mixed>>
     */
    public function discover(): array
    {
        $basePath = base_path('extensions');

        if (! is_dir($basePath)) {
            return [];
        }

        $found = [];

        foreach (new DirectoryIterator($basePath) as $item) {
            if (! $item->isDir() || $item->isDot()) {
                continue;
            }

            // Flat layout: extensions/Name/extension.json
            $manifest = $item->getPathname().'/extension.json';
            if (file_exists($manifest)) {
                $parsed = $this->parseManifest($manifest, $item->getFilename());
                if ($parsed !== null) {
                    $found[] = $parsed;
                }

                continue;
            }

            // Vendor/name layout: extensions/Vendor/Name/extension.json
            foreach (new DirectoryIterator($item->getPathname()) as $sub) {
                if (! $sub->isDir() || $sub->isDot()) {
                    continue;
                }
                $manifest = $sub->getPathname().'/extension.json';
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

            $result = $this->validator->validate($data);

            if (! $result['valid']) {
                // Field names only — never manifest values (could hold secrets).
                Log::info('Extension manifest rejected', [
                    'path' => $relPath,
                    'errors' => $result['errors'],
                ]);

                return null;
            }

            if ($result['warnings'] !== []) {
                Log::info('Extension manifest warnings', [
                    'path' => $relPath,
                    'warnings' => $result['warnings'],
                ]);
            }

            // "id" is the official identity field; "slug" stays the internal key.
            $data['slug'] = $data['slug'] ?? $data['id'];

            return array_merge($data, ['path' => $relPath]);
        } catch (Throwable) {
            Log::info('Extension manifest unreadable', ['path' => $relPath]);

            return null;
        }
    }
}
