<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Rebuilds the Vite asset bundle (client + SSR) after an extension is
 * enabled/disabled, or as part of a core update.
 *
 * Dispatched by ExtensionManager so HTTP requests never block on npm.
 * The rebuild state is tracked in cache so the admin UI can show a notice.
 *
 * Cache keys:
 *   assets.rebuild_status  — 'pending' | 'building' | 'done' | 'failed'
 *   assets.rebuild_at      — timestamp of last completed build
 */
class RebuildAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    /**
     * Vite empties its output directory before writing new assets. If the
     * build fails partway through — most commonly because a new dependency
     * landed in package.json but node_modules was never reinstalled — the
     * site is left serving no compiled bundle at all, client or SSR. Every
     * failure here snapshots the working bundle first and restores it, so a
     * broken update never wipes out the assets that were working before it.
     */
    public function handle(): void
    {
        Cache::put('assets.rebuild_status', 'building', now()->addHour());

        $dirs = [public_path('build'), base_path('bootstrap/ssr')];
        $backupDir = storage_path('app/asset-rebuild-backup');

        $this->snapshot($dirs, $backupDir);

        $install = Process::path(base_path())->timeout(300)->run('npm ci 2>&1');

        if (! $install->successful()) {
            $this->markFailed($install->output(), 'npm ci');

            throw new RuntimeException('npm ci failed: '.substr($install->output(), -2000));
        }

        $build = Process::path(base_path())->timeout(540)->run('npm run build 2>&1');

        if (! $build->successful()) {
            $this->restore($dirs, $backupDir);
            $this->markFailed($build->output(), 'npm run build');

            throw new RuntimeException('npm run build failed — previous assets were restored: '.substr($build->output(), -2000));
        }

        File::deleteDirectory($backupDir);

        Cache::put('assets.rebuild_status', 'done', now()->addDay());
        Cache::put('assets.rebuild_at', now()->toIso8601String(), now()->addDay());

        Log::info('Asset rebuild succeeded.');
    }

    public function failed(\Throwable $e): void
    {
        Cache::put('assets.rebuild_status', 'failed', now()->addHour());

        Log::error('RebuildAssetsJob failed', ['exception' => $e->getMessage()]);
    }

    /** @param array<int, string> $dirs */
    private function snapshot(array $dirs, string $backupDir): void
    {
        File::deleteDirectory($backupDir);
        File::ensureDirectoryExists($backupDir);

        foreach ($dirs as $dir) {
            if (File::isDirectory($dir)) {
                File::copyDirectory($dir, $backupDir.'/'.basename($dir));
            }
        }
    }

    /** @param array<int, string> $dirs */
    private function restore(array $dirs, string $backupDir): void
    {
        foreach ($dirs as $dir) {
            $snapshot = $backupDir.'/'.basename($dir);

            if (File::isDirectory($snapshot)) {
                File::deleteDirectory($dir);
                File::copyDirectory($snapshot, $dir);
            }
        }
    }

    private function markFailed(string $output, string $step): void
    {
        Cache::put('assets.rebuild_status', 'failed', now()->addHour());

        Log::error("Asset rebuild failed at {$step}", ['output' => substr($output, -2000)]);
    }
}
