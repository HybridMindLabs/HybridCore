<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Packages an extension into a distributable ZIP that the admin panel's
 * import flow accepts.
 *
 *   php artisan hybridcore:extensions:zip hybridcore/demo
 */
class ExtensionsZipCommand extends Command
{
    /** Never ship these into a distributable archive. */
    private const EXCLUDED = ['.git', 'node_modules', '.gitkeep', '.DS_Store'];

    protected $signature = 'hybridcore:extensions:zip
        {id : Extension id in vendor/name format (e.g. hybridcore/demo)}
        {--out= : Output directory (default: storage/app/extension-builds)}';

    protected $description = 'Package an extension directory into a distributable ZIP';

    public function handle(): int
    {
        $id = strtolower((string) $this->argument('id'));
        $base = $this->resolveBase($id);

        if ($base === null) {
            $this->error("No extension found for \"{$id}\" under extensions/.");

            return self::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($base.'/extension.json'), true);

        if (! is_array($manifest) || ! is_string($manifest['version'] ?? null)) {
            $this->error('extension.json is missing or has no version.');

            return self::FAILURE;
        }

        $outDir = (string) ($this->option('out') ?: storage_path('app/extension-builds'));

        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true)) {
            $this->error("Could not create output directory {$outDir}.");

            return self::FAILURE;
        }

        $zipName = str_replace('/', '-', $id).'-'.$manifest['version'].'.zip';
        $zipPath = rtrim($outDir, '/').'/'.$zipName;

        @unlink($zipPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error("Could not create {$zipPath}.");

            return self::FAILURE;
        }

        $root = basename($base);
        $count = 0;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        ) as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($base) + 1);
            $relative = str_replace('\\', '/', $relative);

            foreach (self::EXCLUDED as $excluded) {
                if (str_contains('/'.$relative.'/', '/'.$excluded.'/') || basename($relative) === $excluded) {
                    continue 2;
                }
            }

            $zip->addFile($file->getPathname(), $root.'/'.$relative);
            $count++;
        }

        $zip->close();

        $this->info("Packaged {$count} file(s) → {$zipPath}");

        return self::SUCCESS;
    }

    /** Find the extension directory for an id, tolerating studly folder names. */
    private function resolveBase(string $id): ?string
    {
        $candidates = [
            base_path('extensions/'.$id),
            base_path('extensions/'.str($id)->replace('/', '/')->explode('/')->map(fn ($p) => str($p)->studly()->toString())->implode('/')),
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && is_file($candidate.'/extension.json')) {
                return $candidate;
            }
        }

        // Fall back to scanning manifests for a matching id/slug.
        foreach (glob(base_path('extensions/*/*/extension.json')) ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest) && (($manifest['id'] ?? $manifest['slug'] ?? null) === $id)) {
                return dirname($manifestPath);
            }
        }

        return null;
    }
}
