<?php

namespace App\Services\Extensions;

use App\Models\Extension;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Discovers newer releases of installed extensions.
 *
 * Applying an update already worked — the ZIP import path validates the
 * manifest, rejects zip-slip and downgrades, runs migrations and republishes
 * assets. What was missing is finding out that a release exists at all, which
 * meant an operator had to follow every author themselves.
 *
 * An extension opts in by declaring an "update_url" in its extension.json. That
 * URL must return JSON:
 *
 *   { "version": "1.2.0",
 *     "download_url": "https://…/my-ext-1.2.0.zip",
 *     "notes": "optional changelog",
 *     "url": "optional human-readable release page" }
 *
 * Both URLs must be HTTPS: the download ends up as executable code, so it must
 * not be interceptable in transit.
 */
class ExtensionUpdateService
{
    private const CACHE_PREFIX = 'hybridcore.extension_update.';

    private const CACHE_TTL = 3600;

    /** A manifest is metadata, not a payload — a large body means a wrong URL. */
    private const MAX_FEED_BYTES = 64 * 1024;

    private const MAX_ARCHIVE_BYTES = 64 * 1024 * 1024;

    public function __construct(private readonly ExtensionManager $manager) {}

    /**
     * The newer release for one extension, or null when it is current, has no
     * feed, or the feed could not be read.
     *
     * @return array{version: string, download_url: string, notes: string, url: string}|null
     */
    public function check(Extension $extension, bool $fresh = false): ?array
    {
        $feedUrl = $this->feedUrl($extension);

        if ($feedUrl === null) {
            return null;
        }

        $key = self::CACHE_PREFIX.$extension->slug;

        if ($fresh) {
            Cache::forget($key);
        }

        $release = Cache::remember($key, self::CACHE_TTL, function () use ($feedUrl) {
            return $this->fetchFeed($feedUrl) ?? [];
        });

        // fetchFeed() returns null for anything unusable and is cached as [],
        // so an empty array is the "no usable release" case.
        if ($release === []) {
            return null;
        }

        if (version_compare((string) $release['version'], (string) $extension->version, '<=')) {
            return null;
        }

        return $release;
    }

    /**
     * Newer releases for every installed extension that declares a feed.
     *
     * @return array<string, array{version: string, download_url: string, notes: string, url: string}>
     */
    public function checkAll(bool $fresh = false): array
    {
        $available = [];

        foreach (Extension::all() as $extension) {
            $release = $this->check($extension, $fresh);

            if ($release !== null) {
                $available[$extension->slug] = $release;
            }
        }

        return $available;
    }

    /**
     * Download the available update and install it through the ordinary import
     * pipeline, so it gets the same manifest validation, zip-slip check and
     * downgrade guard as an archive an administrator uploads by hand.
     *
     * @throws RuntimeException when no update is available or the download fails.
     */
    public function apply(Extension $extension): Extension
    {
        $release = $this->check($extension, fresh: true);

        if ($release === null) {
            throw new RuntimeException('No update is available for this extension.');
        }

        $token = (string) Str::uuid();
        $dir = storage_path('app/extension-imports');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.'/'.$token.'.zip';

        try {
            $this->downloadTo($release['download_url'], $path);
        } catch (Throwable $e) {
            @unlink($path);

            throw new RuntimeException('Could not download the update: '.$e->getMessage());
        }

        // confirmImport consumes the staged file and performs every safety
        // check; it also fires the EXTENSION_UPDATED hook on a version change.
        $updated = $this->manager->confirmImport($token);

        Cache::forget(self::CACHE_PREFIX.$extension->slug);

        return $updated;
    }

    /** The declared feed URL, if it is present and safe to call. */
    private function feedUrl(Extension $extension): ?string
    {
        $url = $extension->metadata['update_url'] ?? null;

        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * @return array{version: string, download_url: string, notes: string, url: string}|null
     */
    private function fetchFeed(string $url): ?array
    {
        try {
            $response = Http::timeout(8)->accept('application/json')->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok() || strlen($response->body()) > self::MAX_FEED_BYTES) {
            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        $version = $data['version'] ?? null;
        $download = $data['download_url'] ?? null;

        // An update we cannot fetch over HTTPS is not an update we can trust.
        if (! is_string($version) || $version === ''
            || ! is_string($download) || ! str_starts_with($download, 'https://')) {
            return null;
        }

        return [
            'version' => ltrim($version, 'v'),
            'download_url' => $download,
            'notes' => mb_substr((string) ($data['notes'] ?? ''), 0, 4000),
            'url' => is_string($data['url'] ?? null) ? $data['url'] : $download,
        ];
    }

    private function downloadTo(string $url, string $path): void
    {
        $response = Http::timeout(60)->withOptions(['stream' => false])->get($url);

        if (! $response->ok()) {
            throw new RuntimeException("the server answered {$response->status()}");
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('the archive is larger than the 64 MB limit');
        }

        if (file_put_contents($path, $body) === false) {
            throw new RuntimeException('the archive could not be written to disk');
        }
    }
}
