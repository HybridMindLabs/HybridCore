<?php

namespace App\Jobs;

use App\Services\ActivityLogService;
use App\Services\DatabaseBackupService;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class RunScheduledBackupJob implements ShouldQueue
{
    use Queueable;

    // mysqldump on a large DB can run well past a typical request timeout —
    // this runs on the queue, not inline in the scheduler tick.
    public int $timeout = 600;

    // No automatic retry: a failed run is picked up by the next scheduled
    // tick (daily at the soonest) rather than hammering a broken mysqldump
    // setup immediately.
    public int $tries = 1;

    /** Cheap check run every minute by the scheduler; only dispatches the heavy job when actually due. */
    public static function isDue(): bool
    {
        $settings = app(SettingsService::class);

        $schedule = $settings->get('backup_schedule', 'off');
        if ($schedule === 'off') {
            return false;
        }

        if (now()->format('H:i') !== (string) $settings->get('backup_time', '03:00')) {
            return false;
        }

        $lastRun = $settings->get('backup_last_run_at');
        if ($lastRun === null) {
            return true;
        }

        $daysSince = Carbon::parse($lastRun)->diffInDays(now());

        return match ($schedule) {
            'daily' => $daysSince >= 1,
            'weekly' => $daysSince >= 7,
            'monthly' => $daysSince >= 28,
            default => false,
        };
    }

    public function handle(DatabaseBackupService $backups, SettingsService $settings, ActivityLogService $activity): void
    {
        $result = $backups->create();

        if (! $result['ok']) {
            $activity->log('backup.scheduled-failed', 'Scheduled database backup failed: '.$result['error']);

            return;
        }

        $settings->set('backup_last_run_at', now()->toIso8601String());

        $deleted = $backups->prune((int) $settings->get('backup_retention', 7));

        $activity->log(
            'backup.scheduled',
            'Scheduled database backup created: '.$result['filename'].($deleted > 0 ? " ({$deleted} old backup(s) pruned)" : '')
        );
    }
}
