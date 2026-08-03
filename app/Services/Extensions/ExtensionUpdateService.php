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
 * An extension opts in by declaring an "update_url" in its extension.json. Two
 * shapes are understood.
 *
 * A GitHub releases endpoint, which is what most authors will point at:
 *
 *   "update_url": "https://api.github.com/repos/vendor/ext/releases/latest"
 *
 * The release's first .zip asset is the package; GitHub's own zipball is a
 * snapshot of the repository, not a built extension, so it is ignored.
 *
 * Or a feed of the author's own, for anyone running their own licensing server:
 *
 *   { "version": "1.2.0",
 *     "download_url": "https://…/my-ext-1.2.0.zip",
 *     "notes": "optional changelog",
 *     "url": "optional human-readable release page" }
 *
 * Paid extensions live in private repositories. When the administrator has
 * stored a license key for one, it travels as a bearer token on both the feed
 * request and the download — which is what a private GitHub repo expects of a
 * personal access token, and what an author's own endpoint can validate as a
 * purchase. Without a key nothing extra is sent, so free extensions are
 * unaffected.
 *
 * Every URL must be HTTPS: the download ends up as executable code, so it must
 * not be interceptable in transit, and a license key must never travel in the
 * clear.
 *
 * HTTPS only proves the bytes weren't altered *in transit* from whatever the
 * feed host currently serves — it says nothing if that host, the author's
 * GitHub account, or their DNS is compromised after install. An author can
 * close that gap by declaring "update_public_key" (a base64 Ed25519 public
 * key) once in extension.json; every release after that must carry a
 * matching "signature" (base64, detached, over the raw ZIP bytes) in their
 * own feed, checked against the key from the *currently installed* manifest —
 * not anything the new release claims — so a compromised feed can serve a
 * new key all it wants and still can't sign with the old private one. This
 * only covers an author's own feed shape; a plain GitHub-releases URL has no
 * field to carry a signature in, so a pinned key with that feed shape always
 * fails closed rather than silently skipping the check.
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
     * @return array{version: string, download_url: string, notes: string, url: string, signature: ?string}|null
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

        $license = $this->licenseKey($extension);

        $release = Cache::remember($key, self::CACHE_TTL, function () use ($feedUrl, $license) {
            return $this->fetchFeed($feedUrl, $license) ?? [];
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
     * @return array<string, array{version: string, download_url: string, notes: string, url: string, signature: ?string}>
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
            $this->downloadTo($release['download_url'], $path, $this->licenseKey($extension));
        } catch (Throwable $e) {
            @unlink($path);

            throw new RuntimeException('Could not download the update: '.$e->getMessage());
        }

        if (! $this->verifySignature($extension, $release, $path)) {
            @unlink($path);

            throw new RuntimeException('The downloaded update failed signature verification.');
        }

        // confirmImport consumes the staged file and performs every safety
        // check; it also fires the EXTENSION_UPDATED hook on a version change.
        $updated = $this->manager->confirmImport($token);

        Cache::forget(self::CACHE_PREFIX.$extension->slug);

        return $updated;
    }

    /** Drop the cached release, so the next check goes back to the network. */
    public function forget(Extension $extension): void
    {
        Cache::forget(self::CACHE_PREFIX.$extension->slug);
    }

    /**
     * If the currently installed manifest pins a public key, the release must
     * carry a valid signature over the downloaded bytes — checked against
     * that pinned key, never anything the release itself supplies. An
     * extension that never opted into signing (no pinned key) is unaffected.
     *
     * @param  array{version: string, download_url: string, notes: string, url: string, signature: ?string}  $release
     */
    private function verifySignature(Extension $extension, array $release, string $path): bool
    {
        $publicKey = $extension->metadata['update_public_key'] ?? null;

        if (! is_string($publicKey) || $publicKey === '') {
            return true;
        }

        $signature = $release['signature'] ?? null;

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        try {
            $key = base64_decode($publicKey, true);
            $sig = base64_decode($signature, true);
            $bytes = file_get_contents($path);

            if ($key === false || $sig === false || $bytes === false) {
                return false;
            }

            return sodium_crypto_sign_verify_detached($sig, $bytes, $key);
        } catch (\SodiumException) {
            return false;
        }
    }

    /** The declared feed URL, if it is present and safe to call. */
    private function feedUrl(Extension $extension): ?string
    {
        $url = $extension->metadata['update_url'] ?? null;

        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    /** The stored credential for a paid extension, if one has been entered. */
    private function licenseKey(Extension $extension): ?string
    {
        $key = trim((string) $extension->license_key);

        return $key === '' ? null : $key;
    }

    /**
     * @return array{version: string, download_url: string, notes: string, url: string, signature: ?string}|null
     */
    private function fetchFeed(string $url, ?string $license = null): ?array
    {
        try {
            $request = Http::timeout(8)->accept('application/json');

            if ($license !== null) {
                $request = $request->withToken($license);
            }

            $response = $request->get($url);
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

        // A GitHub releases endpoint can be pointed at directly, so an author
        // publishing there needs no feed of their own.
        if (isset($data['tag_name'])) {
            $data = $this->fromGitHubRelease($data, $license !== null);
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
            'signature' => is_string($data['signature'] ?? null) ? $data['signature'] : null,
        ];
    }

    /**
     * Map a GitHub release payload onto the shape the rest of this service
     * speaks. The archive is the first .zip asset — GitHub's own zipball is a
     * snapshot of the repository, not the packaged extension, so it is skipped.
     *
     * With a license key we take the asset's API URL rather than its
     * browser_download_url: on a private repository the browser URL redirects
     * to a signed CDN address that ignores the token and answers 404, while the
     * API URL serves the bytes to an authorised caller.
     *
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     */
    private function fromGitHubRelease(array $release, bool $authenticated = false): array
    {
        $zip = null;

        foreach ($release['assets'] ?? [] as $asset) {
            if (! is_array($asset) || ! str_ends_with((string) ($asset['name'] ?? ''), '.zip')) {
                continue;
            }

            $zip = (string) ($authenticated
                ? ($asset['url'] ?? $asset['browser_download_url'] ?? '')
                : ($asset['browser_download_url'] ?? ''));
            break;
        }

        return [
            'version' => $release['tag_name'] ?? null,
            'download_url' => $zip,
            'notes' => $release['body'] ?? '',
            'url' => $release['html_url'] ?? $zip,
        ];
    }

    private function downloadTo(string $url, string $path, ?string $license = null): void
    {
        $request = Http::timeout(60)->withOptions(['stream' => false]);

        if ($license !== null) {
            // GitHub serves a release asset's bytes only when asked for them
            // explicitly; the default Accept returns the asset's JSON metadata.
            $request = $request->withToken($license)->accept('application/octet-stream');
        }

        $response = $request->get($url);

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
