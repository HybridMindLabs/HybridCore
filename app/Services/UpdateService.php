<?php

namespace App\Services;

use App\Http\Controllers\Admin\UpdateController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Checks GitHub Releases for a newer core version. Works for both git-based
 * and ZIP-based installations — it only reads the public releases feed.
 */
class UpdateService
{
    private const CACHE_KEY = 'hybridcore.latest_release';

    private const CACHE_TTL = 3600;

    /**
     * @return array{version: string, name: string, url: string, notes: string, published_at: string|null, prerelease: bool, is_newer: bool}|null
     */
    public function latestRelease(bool $fresh = false): ?array
    {
        // "beta" also offers pre-releases; "stable" and "dev" track the latest
        // full release ("dev" installs follow branch commits on the Update page).
        $beta = app(SettingsService::class)->get('update_channel', 'stable') === 'beta';
        $key = self::CACHE_KEY.($beta ? '.beta' : '');

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_TTL, function () use ($beta) {
            $repo = config('hybridcore.repository');

            try {
                $response = Http::timeout(8)
                    ->withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->get("https://api.github.com/repos/{$repo}/releases".($beta ? '?per_page=10' : '/latest'));
            } catch (\Throwable) {
                return null;
            }

            if (! $response->ok()) {
                return null;
            }

            $release = $beta
                ? collect($response->json())->first(fn ($r) => is_array($r) && ! ($r['draft'] ?? false))
                : $response->json();

            if (! is_array($release)) {
                return null;
            }

            $tag = ltrim((string) ($release['tag_name'] ?? ''), 'v');

            if ($tag === '') {
                return null;
            }

            return [
                'version' => $tag,
                'name' => (string) ($release['name'] ?? $tag),
                'url' => (string) ($release['html_url'] ?? ''),
                'notes' => mb_substr((string) ($release['body'] ?? ''), 0, 4000),
                'published_at' => $release['published_at'] ?? null,
                'prerelease' => (bool) ($release['prerelease'] ?? false),
                'is_newer' => version_compare($tag, UpdateController::VERSION, '>'),
            ];
        });
    }

    /** Drop the cached release for every channel (after the channel changes). */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY.'.beta');
    }
}
