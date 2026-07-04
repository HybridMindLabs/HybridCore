<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Dispatched every minute by the scheduler (routes/console.php) so the admin
 * health page can tell whether a queue worker is actually consuming jobs.
 * If no worker is running, this job never executes and the cache key goes
 * stale — that staleness is the signal, not the job's own logic.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 10;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put('health.queue_worker_last_run', now()->toIso8601String(), now()->addHour());
    }
}
