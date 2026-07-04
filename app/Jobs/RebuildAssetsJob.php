<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Rebuilds the Vite asset bundle after an extension is enabled or disabled.
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

    public int $timeout = 600;

    public int $tries = 2;

    public function handle(): void
    {
        Cache::put('assets.rebuild_status', 'building', now()->addHour());

        $result = Process::path(base_path())
            ->timeout(540)
            ->run('npm run build 2>&1');

        if ($result->successful()) {
            Cache::put('assets.rebuild_status', 'done', now()->addDay());
            Cache::put('assets.rebuild_at', now()->toIso8601String(), now()->addDay());

            Log::info('Extension asset rebuild succeeded.');
        } else {
            Cache::put('assets.rebuild_status', 'failed', now()->addHour());

            Log::error('Extension asset rebuild failed', [
                'output' => substr($result->output(), -2000),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put('assets.rebuild_status', 'failed', now()->addHour());

        Log::error('RebuildAssetsJob failed', ['exception' => $e->getMessage()]);
    }
}
