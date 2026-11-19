<?php

namespace App\Services\Update;

use App\Notifications\SystemNotification;
use App\Services\ActivityLogService;
use App\Services\AdminNotifier;
use App\Services\WebhookDispatcher;
use Throwable;

/**
 * What happens once an update, a failed update or a rollback is over: the
 * full log is kept on disk, the activity log gets an entry, the staff get a
 * notification and webhooks fire. A failure here never fails the update.
 */
class UpdateReporter
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly AdminNotifier $notifier,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $properties
     * @return string|null the log file name under storage/app/updates
     */
    public function report(string $event, string $description, array $result, array $properties): ?string
    {
        $logFile = $this->persistLog($event, $result);

        try {
            $this->activity->log($event, $description, null, [...$properties, 'log_file' => $logFile, 'seconds' => $result['seconds']]);
            $this->notify($event, $result);
        } catch (Throwable $e) {
            report($e);
        }

        return $logFile;
    }

    /** @param  array<string, mixed>  $result */
    private function persistLog(string $event, array $result): ?string
    {
        try {
            $name = 'update-'.now()->format('Y-m-d-His').'.json';
            $dir = storage_path('app/updates');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir.'/'.$name, json_encode(
                ['event' => $event, 'at' => now()->toIso8601String(), ...$result],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $name;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** @param  array<string, mixed>  $result */
    private function notify(string $event, array $result): void
    {
        $ok = $result['success'];
        $what = match ($event) {
            'system.reverted' => "HybridCore was rolled back to {$result['to']}",
            'system.updated' => $result['from'] === $result['to'] ? 'The system update finished (no new code)' : "HybridCore was updated from {$result['from']} to {$result['to']}",
            default => 'The system update failed'.($result['rolled_back'] ? ' — the previous version was restored' : ''),
        };

        $this->notifier->send('system_update', new SystemNotification(
            message: $what.' ('.$result['seconds'].'s).',
            level: $ok ? 'success' : 'danger',
            actionUrl: route('admin.updates.index'),
            actionLabel: 'Open System Update',
        ));

        $this->webhooks->dispatch($event, [
            'success' => $ok,
            'message' => $result['message'],
            'from' => $result['from'],
            'to' => $result['to'],
            'rolled_back' => $result['rolled_back'],
            'seconds' => $result['seconds'],
        ]);
    }
}
