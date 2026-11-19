<?php

namespace App\Services\Extensions;

use App\Models\Extension;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * What an extension's manifest asks for when it is turned on or removed:
 * its migrations, its seeder and its public assets.
 *
 * Every folder the manifest names must stay inside the extension. The
 * validator refuses such manifests on upload; this checks again because the
 * metadata of an extension copied onto the disk never went through upload.
 */
class ExtensionSetup
{
    /** Run the extension's pending migrations. */
    public function migrate(Extension $extension): void
    {
        $path = $this->folder($extension, 'migrations');
        if ($path === null) {
            return;
        }

        $exitCode = Artisan::call('migrate', ['--path' => $path, '--force' => true]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Migrations failed: '.trim(Artisan::output()));
        }
    }

    /** Roll back every migration the extension ever ran (drops its tables). */
    public function rollback(Extension $extension): void
    {
        $path = $this->folder($extension, 'migrations');
        if ($path === null) {
            return;
        }

        Artisan::call('migrate:reset', ['--path' => $path, '--force' => true]);
    }

    /**
     * Run the extension's declared seeder class (manifest "seeder"). Idempotent
     * seeders are the extension author's responsibility — enable can run more
     * than once.
     */
    public function seed(Extension $extension): void
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
     * Copy the extension's static assets (manifest "assets", e.g.
     * "resources/assets") to public/extensions/{vendor-name}/ so images and
     * CSS are web-reachable without touching the build pipeline.
     */
    public function publishAssets(Extension $extension): void
    {
        $path = $this->folder($extension, 'assets');
        if ($path === null) {
            return;
        }

        $target = $this->publicAssets($extension);

        File::deleteDirectory($target);
        File::copyDirectory(base_path($path), $target);
    }

    public function unpublishAssets(Extension $extension): void
    {
        $target = $this->publicAssets($extension);

        if (is_dir($target)) {
            File::deleteDirectory($target);
        }
    }

    private function publicAssets(Extension $extension): string
    {
        return public_path('extensions/'.str_replace('/', '-', $extension->slug));
    }

    /**
     * The manifest folder as a path relative to the app, or null when it is
     * not declared, does not exist or resolves outside the extension.
     */
    private function folder(Extension $extension, string $key): ?string
    {
        $dir = ($extension->metadata ?? [])[$key] ?? null;

        if (! is_string($dir) || ! ExtensionManifestValidator::isInsidePath($dir)) {
            return null;
        }

        $relative = 'extensions/'.$extension->path.'/'.trim($dir, '/');
        $root = realpath(base_path('extensions/'.$extension->path));
        $resolved = realpath(base_path($relative));

        // realpath() also follows symlinks, so a link pointing elsewhere fails here.
        if ($root === false || $resolved === false || ! is_dir($resolved)
            || ($resolved !== $root && ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR))) {
            return null;
        }

        return $relative;
    }
}
