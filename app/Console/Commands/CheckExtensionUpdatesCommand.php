<?php

namespace App\Console\Commands;

use App\Models\Extension;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\Extensions\ExtensionUpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Daily poll of every installed extension's update feed.
 *
 * The admin page already shows what is newer, but only for whoever opens it.
 * This is the counterpart to hybridcore:update:check for the core, so an
 * extension release is noticed without anyone going looking.
 */
class CheckExtensionUpdatesCommand extends Command
{
    protected $signature = 'hybridcore:extensions:check-updates
        {--notify : Notify administrators about versions not announced yet}';

    protected $description = 'Check installed extensions for newer releases';

    /** Remembers what has been announced, keyed per extension. */
    private const ANNOUNCED_PREFIX = 'hybridcore.extension_update_announced.';

    public function handle(ExtensionUpdateService $updates): int
    {
        $available = $updates->checkAll(fresh: true);

        if ($available === []) {
            $this->info('All extensions are up to date.');

            return self::SUCCESS;
        }

        foreach ($available as $slug => $release) {
            $this->warn("{$slug}: {$release['version']} available");
        }

        if ($this->option('notify')) {
            $this->announce($available);
        }

        return self::SUCCESS;
    }

    /** @param array<string, array{version: string, url: string}> $available */
    private function announce(array $available): void
    {
        $fresh = [];

        foreach ($available as $slug => $release) {
            if (Cache::get(self::ANNOUNCED_PREFIX.$slug) !== $release['version']) {
                $fresh[$slug] = $release;
            }
        }

        if ($fresh === []) {
            $this->line('Nothing new since the last announcement.');

            return;
        }

        $names = Extension::whereIn('slug', array_keys($fresh))->pluck('name', 'slug');

        $list = collect($fresh)
            ->map(fn (array $r, string $slug) => ($names[$slug] ?? $slug).' '.$r['version'])
            ->implode(', ');

        // One notification for the batch: a nightly message per extension would
        // be noise on an install with several of them.
        foreach (User::where('is_admin', true)->get() as $admin) {
            $admin->notify(new SystemNotification(
                'Extension updates available: '.$list,
                'info',
                route('admin.extensions.index'),
                'Review extensions',
            ));
        }

        foreach ($fresh as $slug => $release) {
            Cache::forever(self::ANNOUNCED_PREFIX.$slug, $release['version']);
        }

        $this->info('Administrators notified about '.count($fresh).' extension update(s).');
    }
}
