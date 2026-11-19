<?php

namespace App\Services\Extensions;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reading, checking and unpacking an uploaded extension ZIP: its manifest is
 * validated, and anything that could write outside the target folder, plant
 * a symlink or expand into something far larger than it looks is refused.
 */
class ExtensionArchive
{
    /** Ceilings on what an uploaded archive may expand to (zip-bomb guard). */
    public const MAX_ENTRIES = 5000;

    public const MAX_UNCOMPRESSED_BYTES = 100 * 1024 * 1024;

    /** A previewed archive the admin never confirmed is dropped after this long. */
    private const STAGED_TTL_SECONDS = 86400;

    public function __construct(
        private readonly ExtensionManifestValidator $validator,
        private readonly ExtensionRequirements $requirements,
    ) {}

    /**
     * Open a ZIP, locate + parse + validate its manifest and check every entry.
     * Returns the still-open ZipArchive so the caller decides whether to
     * extract it now or stash it for later.
     *
     * @return array{0: ZipArchive, 1: array<string, mixed>, 2: array<int, string>}
     */
    public function open(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the uploaded file as a ZIP archive.');
        }

        try {
            $manifestEntry = $this->findManifestEntry($zip)
                ?? throw new RuntimeException('No extension.json found in the archive.');

            $manifestJson = $zip->getFromName($manifestEntry);
            $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;

            if (! is_array($manifest)) {
                throw new RuntimeException('extension.json is not valid JSON.');
            }

            $result = $this->validator->validate($manifest);

            if (! $result['valid']) {
                throw new RuntimeException('Invalid manifest: '.implode(', ', $result['errors']));
            }

            $requirementErrors = $this->requirements->check($manifest);

            if ($requirementErrors !== []) {
                throw new RuntimeException('Extension '.implode('; ', $requirementErrors).'.');
            }

            $this->assertSafe($zip);
        } catch (RuntimeException $e) {
            $zip->close();

            throw $e;
        }

        $manifest['_manifest_entry'] = $manifestEntry;

        return [$zip, $manifest, $result['warnings']];
    }

    /** Keep an uploaded archive under a random token until the admin confirms it. */
    public function stage(string $path): string
    {
        $this->pruneStaged();

        $token = (string) Str::uuid();
        $dir = $this->stagingDir();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! copy($path, $dir.'/'.$token.'.zip')) {
            throw new RuntimeException('Could not stage the archive for import.');
        }

        return $token;
    }

    /** The staged archive for a token, or null once it was used or expired. */
    public function staged(string $token): ?string
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $path = $this->stagingDir().'/'.$token.'.zip';

        return is_file($path) ? $path : null;
    }

    /**
     * Unpack the archive into $targetPath. Entries are taken from under the
     * folder that holds extension.json, so the contents land directly inside.
     *
     * @param  array<string, mixed>  $manifest  as returned by open()
     */
    public function extract(ZipArchive $zip, array $manifest, string $targetPath): void
    {
        $manifestEntry = $manifest['_manifest_entry'];
        $root = str($manifestEntry)->beforeLast('/')->toString();
        $root = $root === $manifestEntry ? '' : $root.'/';

        if (! mkdir($targetPath, 0755, true) && ! is_dir($targetPath)) {
            throw new RuntimeException("Could not create extensions directory at {$targetPath}.");
        }

        $resolvedTarget = realpath($targetPath);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || ($root !== '' && ! str_starts_with($name, $root))) {
                continue;
            }

            $relative = $root === '' ? $name : substr($name, strlen($root));

            if ($relative === '') {
                continue;
            }

            $destination = $targetPath.'/'.$relative;

            if (str_ends_with($name, '/')) {
                if (! is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }

                continue;
            }

            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }

            // Re-check after mkdir: destination must still resolve inside targetPath.
            $resolvedDir = realpath(dirname($destination));

            if ($resolvedDir === false || $resolvedTarget === false
                || ($resolvedDir !== $resolvedTarget && ! str_starts_with($resolvedDir, $resolvedTarget.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Archive entry resolved outside the extension directory: '.$name);
            }

            $contents = $zip->getFromIndex($i);

            if ($contents !== false) {
                file_put_contents($destination, $contents);
            }
        }
    }

    private function findManifestEntry(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && basename($name) === 'extension.json' && substr_count($name, '/') <= 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Reject archives that could write outside the extension folder, plant a
     * symlink, or expand into something far larger than they look.
     */
    private function assertSafe(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new RuntimeException('Archive has too many files ('.$zip->numFiles.'; the limit is '.self::MAX_ENTRIES.').');
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                throw new RuntimeException('Archive contains an unreadable entry.');
            }

            $name = $stat['name'];

            if (str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, '..')
                || preg_match('/^[A-Za-z]:/', $name) === 1 || str_contains($name, "\0")) {
                throw new RuntimeException('Archive contains an unsafe path: '.$name);
            }

            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                && $opsys === ZipArchive::OPSYS_UNIX
                && (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('Archive contains a symbolic link: '.$name);
            }

            $total += (int) $stat['size'];

            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('Archive expands to more than '.(self::MAX_UNCOMPRESSED_BYTES / 1024 / 1024).' MB.');
            }
        }
    }

    private function stagingDir(): string
    {
        return storage_path('app/extension-imports');
    }

    /** Previews nobody confirmed would otherwise pile up in storage forever. */
    private function pruneStaged(): void
    {
        foreach (glob($this->stagingDir().'/*.zip') ?: [] as $file) {
            if (filemtime($file) < time() - self::STAGED_TTL_SECONDS) {
                @unlink($file);
            }
        }
    }
}
