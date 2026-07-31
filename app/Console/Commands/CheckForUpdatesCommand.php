<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\UpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Looks for a newer core release and tells the administrators about it.
 *
 * Without this nobody learns a release exists until someone happens to open
 * the updates page — which, for a security release, is exactly the wrong way
 * to find out.
 */
class CheckForUpdatesCommand extends Command
{
    protected $signature = 'hybridcore:update:check
        {--notify : Send a notification to administrators when a newer release exists}';

    protected $description = 'Check GitHub for a newer HybridCore release';

    /** Remembers the version we already announced, so admins are told once. */
    private const ANNOUNCED_KEY = 'hybridcore.update_announced_version';

    public function handle(UpdateService $updates): int
    {
        $release = $updates->latestRelease(fresh: true);

        if ($release === null) {
            $this->warn('Could not reach the release feed — leaving the cached result alone.');

            // Not a failure: an unreachable feed must never turn a scheduled
            // run red, or the noise trains people to ignore it.
            return self::SUCCESS;
        }

        $this->line('Installed: '.UpdateController::VERSION);
        $this->line("Latest:    {$release['version']}");

        if (! $release['is_newer']) {
            $this->info('Up to date.');

            return self::SUCCESS;
        }

        $this->warn("Update available: {$release['version']}");

        if ($this->option('notify')) {
            $this->announce($release);
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $release */
    private function announce(array $release): void
    {
        $version = (string) $release['version'];

        if (Cache::get(self::ANNOUNCED_KEY) === $version) {
            $this->line('Already announced this version.');

            return;
        }

        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            // Plain English to match the rest of the admin panel, which is not
            // translated.
            $admin->notify(new SystemNotification(
                "HybridCore {$version} is available. You are running ".UpdateController::VERSION.'.',
                'info',
                route('admin.updates.index'),
                'View update',
            ));
        }

        Cache::forever(self::ANNOUNCED_KEY, $version);

        $this->info("Notified {$admins->count()} administrator(s).");
    }
}
