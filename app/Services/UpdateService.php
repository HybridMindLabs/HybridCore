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
     * @return array{version: string, name: string, url: string, notes: string, published_at: string|null, is_newer: bool}|null
     */
    public function latestRelease(bool $fresh = false): ?array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $repo = config('hybridcore.repository');

            try {
                $response = Http::timeout(8)
                    ->withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");
            } catch (\Throwable) {
                return null;
            }

            if (! $response->ok()) {
                return null;
            }

            $tag = ltrim((string) $response->json('tag_name'), 'v');

            if ($tag === '') {
                return null;
            }

            return [
                'version' => $tag,
                'name' => (string) $response->json('name', $tag),
                'url' => (string) $response->json('html_url', ''),
                'notes' => mb_substr((string) $response->json('body', ''), 0, 4000),
                'published_at' => $response->json('published_at'),
                'is_newer' => version_compare($tag, UpdateController::VERSION, '>'),
            ];
        });
    }
}
