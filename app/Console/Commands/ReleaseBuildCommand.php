<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\UpdateController;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Builds a distributable release archive of the core.
 *
 *   php artisan hybridcore:release                 # full build (assets + zip)
 *   php artisan hybridcore:release --no-build      # skip npm run build
 *
 * The archive contains everything a hosting upload needs: sources, compiled
 * assets and the storage/bootstrap skeletons — but no dev tooling, no .env,
 * no .git and no vendor/ (installed on the server via composer, or ship it
 * with --with-vendor).
 */
class ReleaseBuildCommand extends Command
{
    /** Top-level entries that never ship in a release. */
    private const EXCLUDED_ROOTS = [
        '.git', '.github', '.ddev', '.idea', '.vscode',
        'node_modules', 'tests', 'test-results', 'playwright-report', 'docs',
        'vendor', // included only with --with-vendor
    ];

    /** File names excluded anywhere in the tree. */
    private const EXCLUDED_FILES = [
        '.env', '.env.backup', '.env.production', '.DS_Store',
        'phpunit.xml', 'playwright.config.ts',
    ];

    /** Runtime directories shipped empty (skeleton only). */
    private const RUNTIME_DIRS = [
        'storage/app/public', 'storage/app/private', 'storage/framework/cache',
        'storage/framework/sessions', 'storage/framework/views', 'storage/logs',
        'bootstrap/cache',
    ];

    protected $signature = 'hybridcore:release
        {--no-build : Skip npm run build (assets already compiled)}
        {--with-vendor : Bundle vendor/ so the server does not need composer}
        {--out= : Output directory (default: storage/app/releases)}';

    protected $description = 'Package the core into a distributable release ZIP';

    public function handle(): int
    {
        $version = UpdateController::VERSION;

        if (! $this->option('no-build')) {
            $this->components->info('Building frontend assets…');
            $build = new Process(['npm', 'run', 'build'], base_path(), null, null, 600);
            $build->run();

            if (! $build->isSuccessful()) {
                $this->components->error('npm run build failed:');
                $this->line($build->getErrorOutput());

                return self::FAILURE;
            }
        }

        $outDir = (string) ($this->option('out') ?: storage_path('app/releases'));

        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true)) {
            $this->components->error("Could not create output directory {$outDir}.");

            return self::FAILURE;
        }

        $zipPath = rtrim($outDir, '/')."/hybridcore-{$version}.zip";
        @unlink($zipPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->components->error("Could not create {$zipPath}.");

            return self::FAILURE;
        }

        $count = $this->addProjectFiles($zip);
        $this->addRuntimeSkeleton($zip);
        $zip->close();

        $size = round(filesize($zipPath) / 1024 / 1024, 1);
        $this->components->info("Packaged {$count} files → {$zipPath} ({$size} MB)");
        $this->line('Ship it with: unzip → copy .env.example to .env → open /install (or php artisan hybridcore:update --local for upgrades).');

        return self::SUCCESS;
    }

    private function addProjectFiles(ZipArchive $zip): int
    {
        $base = base_path();
        $count = 0;
        $withVendor = (bool) $this->option('with-vendor');

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        ) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

            if ($this->isExcluded($relative, $withVendor)) {
                continue;
            }

            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'hybridcore/'.$relative);
                $count++;
            }
        }

        return $count;
    }

    /** Decides whether a repo-relative path stays out of the archive. */
    public function isExcluded(string $relative, bool $withVendor = false): bool
    {
        $root = explode('/', $relative, 2)[0];

        if (in_array($root, self::EXCLUDED_ROOTS, true) && ! ($withVendor && $root === 'vendor')) {
            return true;
        }

        if (in_array(basename($relative), self::EXCLUDED_FILES, true)) {
            return true;
        }

        // Runtime state never ships — the skeleton is added separately.
        foreach (['storage/', 'bootstrap/cache/'] as $runtime) {
            if (str_starts_with($relative, $runtime)) {
                return true;
            }
        }

        // Local extensions are the site owner's content, not the core's.
        if (str_starts_with($relative, 'extensions/') && ! str_starts_with($relative, 'extensions/BUILDING_EXTENSIONS.md')) {
            return true;
        }

        return false;
    }

    private function addRuntimeSkeleton(ZipArchive $zip): void
    {
        foreach (self::RUNTIME_DIRS as $dir) {
            $zip->addFromString('hybridcore/'.$dir.'/.gitignore', "*\n!.gitignore\n");
        }
    }
}
