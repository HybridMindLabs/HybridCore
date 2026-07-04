<?php

namespace App\Services\Extensions;

use App\Jobs\RebuildAssetsJob;
use App\Models\Extension;
use App\Services\ActivityLogService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExtensionManager
{
    public function __construct(
        private readonly ExtensionDiscoveryService $discovery,
        private readonly ExtensionManifestValidator $validator,
        private readonly ExtensionRequirements $requirements,
        private readonly ActivityLogService $activity,
        private readonly HookRegistry $hooks,
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
        $count = 0;

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
            $count++;
        }

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

        $this->activity->log('extension.enabled', "Enabled extension: {$extension->name}", $extension);

        $this->runMigrations($extension);
        $this->runSeeder($extension);
        $this->publishAssets($extension);
        $this->dispatchRebuild();
        $this->hooks->fire(Hooks::EXTENSION_ENABLED, $extension);
    }

    /**
     * Copy the extension's static assets (manifest "assets", e.g.
     * "resources/assets") to public/extensions/{vendor-name}/ so images and
     * CSS are web-reachable without touching the build pipeline.
     */
    public function publishAssets(Extension $extension): void
    {
        $manifest = $extension->metadata ?? [];
        $dir = $manifest['assets'] ?? null;

        if (! is_string($dir) || $dir === '') {
            return;
        }

        $source = base_path('extensions/'.$extension->path.'/'.$dir);

        if (! is_dir($source)) {
            return;
        }

        $target = public_path('extensions/'.str_replace('/', '-', $extension->slug));

        File::deleteDirectory($target);
        File::copyDirectory($source, $target);
    }

    private function unpublishAssets(Extension $extension): void
    {
        $target = public_path('extensions/'.str_replace('/', '-', $extension->slug));

        if (is_dir($target)) {
            File::deleteDirectory($target);
        }
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
            $this->rollbackMigrations($extension);
        }

        $extension->settings()->delete();
        $this->unpublishAssets($extension);

        $base = base_path('extensions/'.$extension->path);

        // Only ever delete inside extensions/ — never follow a stray path.
        if (is_dir($base) && str_starts_with((string) realpath($base), (string) realpath(base_path('extensions')))) {
            File::deleteDirectory($base);
        }

        $name = $extension->name;
        $extension->delete();

        $this->activity->log('extension.uninstalled', "Uninstalled extension: {$name}".($dropData ? ' (data dropped)' : ' (data kept)'));

        $this->dispatchRebuild();
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

    /** @return Collection<int, Extension> */
    public function getEnabled(): Collection
    {
        return Extension::where('enabled', true)->get();
    }

    /** @return Collection<int, Extension> */
    public function getInstalled(): Collection
    {
        return Extension::whereNotNull('installed_at')->get();
    }

    /**
     * Run pending migrations for a specific extension on enable().
     * This is synchronous but fast — migrations rarely take more than a second.
     */
    public function runMigrations(Extension $extension): void
    {
        $manifest = $extension->metadata ?? [];
        $migrationsDir = $manifest['migrations'] ?? null;

        if (! is_string($migrationsDir) || $migrationsDir === '') {
            return;
        }

        $path = 'extensions/'.$extension->path.'/'.$migrationsDir;

        if (! is_dir(base_path($path))) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $path,
            '--force' => true,
        ]);
    }

    /** Roll back every migration the extension ever ran (drops its tables). */
    private function rollbackMigrations(Extension $extension): void
    {
        $manifest = $extension->metadata ?? [];
        $migrationsDir = $manifest['migrations'] ?? null;

        if (! is_string($migrationsDir) || $migrationsDir === '') {
            return;
        }

        $path = 'extensions/'.$extension->path.'/'.$migrationsDir;

        if (! is_dir(base_path($path))) {
            return;
        }

        Artisan::call('migrate:reset', [
            '--path' => $path,
            '--force' => true,
        ]);
    }

    /**
     * Run the extension's declared seeder class (manifest "seeder") after
     * enable. Idempotent seeders are the extension author's responsibility —
     * enable can run more than once.
     */
    private function runSeeder(Extension $extension): void
    {
        $manifest = $extension->metadata ?? [];
        $seeder = $manifest['seeder'] ?? null;

        if (! is_string($seeder) || $seeder === '') {
            return;
        }

        // The extension may have been disabled at boot, so its autoloader
        // isn't registered yet in this request.
        ExtensionAutoloader::register(base_path('extensions/'.$extension->path), $manifest);

        if (class_exists($seeder)) {
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        }
    }

    /**
     * Dispatch an async asset rebuild job.
     *
     * The job runs `npm run build` in the background so the HTTP request
     * returns immediately. The admin UI polls assets.rebuild_status to show
     * progress. PHP-only extensions (no Vue components) don't need this,
     * but we dispatch it anyway for safety — the job is cheap when nothing changed.
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

    /**
     * Parse and validate an uploaded ZIP's manifest without extracting
     * anything, then stash the archive under a random token so a follow-up
     * confirmImport() call can finish the job the admin actually approved.
     *
     * @return array{token: string, manifest: array<string, mixed>, warnings: array<int, string>}
     *
     * @throws RuntimeException on any validation failure.
     */
    public function previewZip(UploadedFile $file): array
    {
        [$zip, $manifest, $warnings] = $this->openAndValidate($file->getRealPath());
        $zip->close();

        $token = (string) Str::uuid();
        $tempDir = storage_path('app/extension-imports');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if (! copy($file->getRealPath(), $tempDir.'/'.$token.'.zip')) {
            throw new RuntimeException('Could not stage the archive for import.');
        }

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
        $tempPath = storage_path('app/extension-imports/'.basename($token).'.zip');

        if (! is_file($tempPath)) {
            throw new RuntimeException('This import has expired — please upload the archive again.');
        }

        try {
            [$zip, $manifest] = $this->openAndValidate($tempPath);

            try {
                $this->extractValidated($zip, $manifest);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($tempPath);
        }

        $this->activity->log('extensions.imported', "Imported extension from ZIP: {$manifest['name']}");

        $this->sync();

        return Extension::where('slug', $manifest['slug'] ?? $manifest['id'])->firstOrFail();
    }

    /**
     * Extract an uploaded extension ZIP into extensions/ and sync it into
     * the database in one step (no preview). Kept for callers that don't
     * need a confirmation round-trip.
     *
     * @throws RuntimeException on any validation/extraction failure.
     */
    public function importZip(UploadedFile $file): Extension
    {
        [$zip, $manifest] = $this->openAndValidate($file->getRealPath());

        try {
            $this->extractValidated($zip, $manifest);
        } finally {
            $zip->close();
        }

        $this->activity->log('extensions.imported', "Imported extension from ZIP: {$manifest['name']}");

        $this->sync();

        return Extension::where('slug', $manifest['slug'] ?? $manifest['id'])->firstOrFail();
    }

    /**
     * Open a ZIP, locate + parse + validate its manifest, and check for
     * zip-slip. Returns the still-open ZipArchive so the caller decides
     * whether to extract it now or stash it for later.
     *
     * @return array{0: ZipArchive, 1: array<string, mixed>, 2: array<int, string>}
     */
    private function openAndValidate(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the uploaded file as a ZIP archive.');
        }

        $manifestEntry = $this->findManifestEntry($zip);

        if ($manifestEntry === null) {
            $zip->close();
            throw new RuntimeException('No extension.json found in the archive.');
        }

        $manifestJson = $zip->getFromName($manifestEntry);
        $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;

        if (! is_array($manifest)) {
            $zip->close();
            throw new RuntimeException('extension.json is not valid JSON.');
        }

        $result = $this->validator->validate($manifest);

        if (! $result['valid']) {
            $zip->close();
            throw new RuntimeException('Invalid manifest: '.implode(', ', $result['errors']));
        }

        $requirementErrors = $this->requirements->check($manifest);

        if ($requirementErrors !== []) {
            $zip->close();
            throw new RuntimeException('Extension '.implode('; ', $requirementErrors).'.');
        }

        $this->assertNoZipSlip($zip);

        $manifest['_manifest_entry'] = $manifestEntry;

        return [$zip, $manifest, $result['warnings']];
    }

    /** Extract a ZIP already opened + validated by openAndValidate(). */
    private function extractValidated(ZipArchive $zip, array $manifest): void
    {
        $manifestEntry = $manifest['_manifest_entry'];

        // Entries live under "<root>/extension.json" — strip that root prefix
        // so the archive's contents land directly inside the target folder.
        $root = str($manifestEntry)->beforeLast('/')->toString();
        $root = $root === $manifestEntry ? '' : $root.'/';

        $id = $manifest['id'] ?? $manifest['slug'];
        $targetRelative = str_contains($id, '/') ? $id : Str::studly($id);
        $targetPath = base_path('extensions/'.$targetRelative);

        $existing = Extension::where('slug', $manifest['slug'] ?? $id)->first();

        if (is_dir($targetPath) && $existing === null) {
            throw new RuntimeException("An extension already exists at extensions/{$targetRelative} — remove it first to reimport.");
        }

        // Update path: same extension reimported — allow only equal or newer
        // versions, replace the files, and run any new migrations if enabled.
        if ($existing !== null) {
            if (version_compare($manifest['version'], $existing->version, '<')) {
                throw new RuntimeException(
                    "The archive contains {$manifest['version']} but {$existing->version} is installed — downgrades are not supported."
                );
            }

            if (is_dir($targetPath)) {
                File::deleteDirectory($targetPath);
            }

            $this->extractTo($zip, $root, $targetPath);

            $oldVersion = $existing->version;
            $this->sync();
            $existing->refresh();

            if ($existing->enabled) {
                $this->runMigrations($existing);
                $this->runSeeder($existing);
                $this->publishAssets($existing);
            }

            $this->activity->log('extension.updated', "Updated extension {$existing->name}: {$oldVersion} → {$manifest['version']}", $existing);
            $this->hooks->fire(Hooks::EXTENSION_UPDATED, $existing, $oldVersion);

            return;
        }

        $this->extractTo($zip, $root, $targetPath);
    }

    private function findManifestEntry(ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && (basename($name) === 'extension.json') && substr_count($name, '/') <= 1) {
                return $name;
            }
        }

        return null;
    }

    /** Reject any entry that would escape the extraction directory. */
    private function assertNoZipSlip(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            if (str_starts_with($name, '/') || str_contains($name, '..')) {
                throw new RuntimeException('Archive contains an unsafe path: '.$name);
            }
        }
    }

    private function extractTo(ZipArchive $zip, string $root, string $targetPath): void
    {
        if (! mkdir($targetPath, 0755, true) && ! is_dir($targetPath)) {
            throw new RuntimeException("Could not create extensions directory at {$targetPath}.");
        }

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
            $resolvedTarget = realpath($targetPath);

            if ($resolvedDir === false || $resolvedTarget === false || ! str_starts_with($resolvedDir, $resolvedTarget)) {
                throw new RuntimeException('Archive entry resolved outside the extension directory: '.$name);
            }

            $contents = $zip->getFromIndex($i);

            if ($contents !== false) {
                file_put_contents($destination, $contents);
            }
        }
    }
}
