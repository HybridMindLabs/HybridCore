<?php

namespace App\Services\Extensions;

use App\Jobs\RebuildAssetsJob;
use App\Models\Extension;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * An extension's lifecycle: discovered on disk, enabled, disabled, updated
 * from an uploaded archive and uninstalled. Unpacking and checking archives
 * lives in ExtensionArchive, the manifest's migrations/seeder/assets in
 * ExtensionSetup.
 */
class ExtensionManager
{
    /** @deprecated use ExtensionArchive::MAX_ENTRIES */
    public const MAX_ARCHIVE_ENTRIES = ExtensionArchive::MAX_ENTRIES;

    /** @deprecated use ExtensionArchive::MAX_UNCOMPRESSED_BYTES */
    public const MAX_UNCOMPRESSED_BYTES = ExtensionArchive::MAX_UNCOMPRESSED_BYTES;

    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
        private readonly ExtensionRequirements $requirements,
        private readonly ActivityLogService $activity,
        private readonly HookRegistry $hooks,
        private readonly ExtensionArchive $archive,
        private readonly ExtensionSetup $setup,
    ) {}

    /**
     * Scan the extensions/ directory and upsert discovered extensions into the database.
     * Already-installed extensions retain their enabled/disabled state.
     *
     * @return int Number of extensions synced.
     */
    public function sync(): int
    {
        $manifests = $this->discovery->discover();

        foreach ($manifests as $manifest) {
            Extension::updateOrCreate(
                ['slug' => $manifest['slug']],
                [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'author' => $manifest['author'] ?? 'Unknown',
                    'description' => $manifest['description'] ?? '',
                    'type' => $manifest['type'] ?? 'community',
                    'path' => $manifest['path'],
                    'metadata' => $manifest,
                    'installed_at' => now(),
                ],
            );
        }

        $count = count($manifests);
        $this->activity->log('extensions.synced', "Synced {$count} extension(s) from disk");

        return $count;
    }

    public function enable(Extension $extension): void
    {
        $errors = $this->requirements->check($extension->metadata ?? []);

        if ($errors !== []) {
            throw new RuntimeException("Cannot enable {$extension->name}: ".implode('; ', $errors));
        }

        $extension->update([
            'enabled' => true,
            'enabled_at' => now(),
            'disabled_at' => null,
        ]);

        try {
            $this->setUp($extension);
        } catch (Throwable $e) {
            $extension->update(['enabled' => false, 'enabled_at' => null, 'disabled_at' => now()]);

            throw new RuntimeException("Could not enable {$extension->name}: ".$e->getMessage(), 0, $e);
        }

        $this->activity->log('extension.enabled', "Enabled extension: {$extension->name}", $extension);

        $this->dispatchRebuild();
        $this->hooks->fire(Hooks::EXTENSION_ENABLED, $extension);
    }

    public function disable(Extension $extension): void
    {
        $extension->update([
            'enabled' => false,
            'disabled_at' => now(),
        ]);

        $this->activity->log('extension.disabled', "Disabled extension: {$extension->name}", $extension);

        $this->dispatchRebuild();
        $this->hooks->fire(Hooks::EXTENSION_DISABLED, $extension);
    }

    /**
     * Remove an extension entirely: roll back its migrations (dropping its
     * tables), delete its settings and files, and forget it in the database.
     */
    public function uninstall(Extension $extension, bool $dropData = true): void
    {
        if ($extension->enabled) {
            $this->disable($extension);
        }

        $this->hooks->fire(Hooks::EXTENSION_UNINSTALLED, $extension);

        if ($dropData) {
            $this->setup->rollback($extension);
        }

        $extension->settings()->delete();
        $this->setup->unpublishAssets($extension);

        // Only ever delete a folder inside extensions/ — never follow a stray path.
        $base = realpath(base_path('extensions/'.$extension->path));
        $root = realpath(base_path('extensions'));
        if ($base !== false && $root !== false && is_dir($base) && str_starts_with($base, $root.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($base);
        }

        $name = $extension->name;
        $extension->delete();

        $this->activity->log('extension.uninstalled', "Uninstalled extension: {$name}".($dropData ? ' (data dropped)' : ' (data kept)'));

        $this->dispatchRebuild();
    }

    /** Re-publish the extension's public assets (e.g. after its files changed). */
    public function publishAssets(Extension $extension): void
    {
        $this->setup->publishAssets($extension);
    }

    /**
     * Queue an asset rebuild. The job runs `npm run build` in the background
     * so the request returns immediately; the admin UI polls
     * assets.rebuild_status. Cheap when nothing changed.
     */
    public function dispatchRebuild(): void
    {
        Cache::put('assets.rebuild_status', 'pending', now()->addHour());
        RebuildAssetsJob::dispatch()->onQueue('default');
    }

    /** Return the current rebuild status for the admin UI. */
    public function rebuildStatus(): string
    {
        return Cache::get('assets.rebuild_status', 'done');
    }

    public function lastRebuildAt(): ?string
    {
        return Cache::get('assets.rebuild_at');
    }

    // ── Importing ────────────────────────────────────────────────────

    /**
     * Parse and validate an uploaded ZIP's manifest without extracting
     * anything, then stash the archive under a random token so a follow-up
     * confirmImport() call can finish the job the admin actually approved.
     *
     * @return array{token: string, is_update: bool, current_version: ?string, manifest: array<string, mixed>, warnings: array<int, string>}
     *
     * @throws RuntimeException on any validation failure.
     */
    public function previewZip(UploadedFile $file): array
    {
        [$zip, $manifest, $warnings] = $this->archive->open($file->getRealPath());
        $zip->close();

        $token = $this->archive->stage($file->getRealPath());
        $existing = Extension::where('slug', $manifest['slug'] ?? $manifest['id'] ?? '')->first();

        return [
            'token' => $token,
            'is_update' => $existing !== null,
            'current_version' => $existing?->version,
            'manifest' => [
                'id' => $manifest['id'] ?? $manifest['slug'] ?? null,
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'author' => $manifest['author'] ?? null,
                'description' => $manifest['description'] ?? null,
                'type' => $manifest['type'] ?? 'community',
                'permissions' => $manifest['permissions'] ?? [],
                'routes' => $manifest['routes'] ?? [],
                'migrations' => $manifest['migrations'] ?? null,
                'provider' => $manifest['provider'] ?? null,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Extract a previously previewed archive (by token) into extensions/
     * and sync it into the database. The token is consumed — a second call
     * fails once the staged file has been removed.
     *
     * @throws RuntimeException on any validation/extraction failure.
     */
    public function confirmImport(string $token): Extension
    {
        $path = $this->archive->staged($token)
            ?? throw new RuntimeException('This import has expired — please upload the archive again.');

        try {
            return $this->import($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Extract an uploaded extension ZIP into extensions/ and sync it into
     * the database in one step (no preview).
     *
     * @throws RuntimeException on any validation/extraction failure.
     */
    public function importZip(UploadedFile $file): Extension
    {
        return $this->import($file->getRealPath());
    }

    private function import(string $path): Extension
    {
        [$zip, $manifest] = $this->archive->open($path);

        try {
            $this->install($zip, $manifest);
        } finally {
            $zip->close();
        }

        $this->activity->log('extensions.imported', "Imported extension from ZIP: {$manifest['name']}");

        $this->sync();

        return Extension::where('slug', $manifest['slug'] ?? $manifest['id'])->firstOrFail();
    }

    /**
     * Put an opened archive in place: a fresh install extracts straight into
     * extensions/, a reimport of an installed extension swaps it.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function install(ZipArchive $zip, array $manifest): void
    {
        $id = $manifest['id'] ?? $manifest['slug'];
        $targetRelative = str_contains($id, '/') ? $id : Str::studly($id);
        $targetPath = base_path('extensions/'.$targetRelative);

        $existing = Extension::where('slug', $manifest['slug'] ?? $id)->first();

        if ($existing !== null) {
            $this->update($zip, $manifest, $existing, $targetPath);

            return;
        }

        if (is_dir($targetPath)) {
            throw new RuntimeException("An extension already exists at extensions/{$targetRelative} — remove it first to reimport.");
        }

        $this->archive->extract($zip, $manifest, $targetPath);
    }

    /**
     * Same extension reimported: allow only equal or newer versions, replace
     * the files, and run any new migrations if it is enabled. The new files
     * are staged and swapped in, so a failed extraction or migration puts the
     * working version back instead of leaving the extension half-deleted.
     *
     * @param  array<string, mixed>  $manifest
     */
    private function update(ZipArchive $zip, array $manifest, Extension $existing, string $targetPath): void
    {
        if (version_compare($manifest['version'], $existing->version, '<')) {
            throw new RuntimeException(
                "The archive contains {$manifest['version']} but {$existing->version} is installed — downgrades are not supported."
            );
        }

        // The swap area sits outside extensions/ on purpose: a copy there
        // would be discovered by sync() as a second extension with the same
        // slug and could overwrite the new version's record.
        $swapDir = storage_path('app/extension-swap');
        File::ensureDirectoryExists($swapDir);
        $staging = $swapDir.'/incoming-'.Str::random(12);
        $backup = $swapDir.'/previous-'.Str::random(12);

        try {
            $this->archive->extract($zip, $manifest, $staging);
        } catch (Throwable $e) {
            File::deleteDirectory($staging);

            throw $e;
        }

        if (is_dir($targetPath) && ! rename($targetPath, $backup)) {
            File::deleteDirectory($staging);

            throw new RuntimeException("Could not move the current version of {$existing->name} aside.");
        }

        rename($staging, $targetPath);

        $oldVersion = $existing->version;
        $oldMetadata = $existing->metadata;

        try {
            $this->sync();
            $existing->refresh();

            if ($existing->enabled) {
                $this->setUp($existing);
            }
        } catch (Throwable $e) {
            File::deleteDirectory($targetPath);
            if (is_dir($backup)) {
                rename($backup, $targetPath);
            }
            $existing->forceFill(['version' => $oldVersion, 'metadata' => $oldMetadata])->save();

            throw new RuntimeException("Update of {$existing->name} to {$manifest['version']} failed and was rolled back: ".$e->getMessage(), 0, $e);
        }

        File::deleteDirectory($backup);

        $this->activity->log('extension.updated', "Updated extension {$existing->name}: {$oldVersion} → {$manifest['version']}", $existing);
        $this->hooks->fire(Hooks::EXTENSION_UPDATED, $existing, $oldVersion);
    }

    /** What the manifest asks for when the extension goes live. */
    private function setUp(Extension $extension): void
    {
        $this->setup->migrate($extension);
        $this->setup->seed($extension);
        $this->setup->publishAssets($extension);
    }
}
