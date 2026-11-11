<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\UpdateController;
use App\Services\CoreUpdater;
use Illuminate\Console\Command;

/**
 * Safe, one-shot core update for self-hosted installations.
 *
 *   php artisan hybridcore:update           # git-based install: pulls + upgrades
 *   php artisan hybridcore:update --local   # ZIP-based install: files were already
 *                                           # replaced by hand, run the upgrade steps only
 *
 * Sequence: maintenance on → (git pull) → composer install → migrate →
 * themes synced → caches cleared → queue restarted → maintenance off.
 * Maintenance mode is always lifted again, even when a step fails.
 */
class CoreUpdateCommand extends Command
{
    protected $signature = 'hybridcore:update
        {--local : Skip git pull — files were already replaced (ZIP install)}
        {--no-composer : Skip composer install (vendor/ shipped with the release)}';

    protected $description = 'Update the HybridCore installation to the latest version';

    public function handle(CoreUpdater $updater): int
    {
        $pull = ! $this->option('local');

        $problems = $updater->preflight($pull);
        if ($problems !== []) {
            $this->components->error('The update was not started:');
            $this->components->bulletList($problems);

            return self::FAILURE;
        }

        $this->components->info('Starting HybridCore update…');

        $printed = 0;
        $result = $updater->run(
            pull: $pull,
            composer: ! $this->option('no-composer'),
            build: false,
            onStep: function (array $log) use (&$printed) {
                foreach (array_slice($log, $printed) as $entry) {
                    $this->line(($entry['ok'] ? '<info>✓</info> ' : '<error>✗</error> ')."<comment>{$entry['step']}</comment>");
                    if ($entry['output'] !== '') {
                        $this->line($entry['output']);
                    }
                }
                $printed = count($log);
            },
        );

        if (! $result['success']) {
            $this->components->error('Update failed: '.$result['message']);

            return self::FAILURE;
        }

        $this->components->info('Update complete. Current version: '.UpdateController::VERSION);
        $this->remainingSteps();

        return self::SUCCESS;
    }

    /**
     * What this command deliberately does not do.
     *
     * Building assets needs Node, which plenty of installs do not have because
     * they deploy a release with the build already in it. Restarting a service
     * needs root, and this command must never be a reason to run artisan under
     * sudo. Both are therefore printed rather than performed — and printed
     * loudly, because a stale SSR process fails silently: the site keeps
     * serving, just the previous build's markup.
     */
    private function remainingSteps(): void
    {
        $steps = [];

        if (is_dir(base_path('.git'))) {
            $steps[] = 'Rebuild assets if this install builds its own: npm ci && npm run build';
        }

        if (config('inertia.ssr.enabled')) {
            $steps[] = 'Restart the renderer, which still holds the previous bundle in memory: sudo systemctl restart hybridcore-ssr';
        }

        if ($steps === []) {
            return;
        }

        $this->newLine();
        $this->components->warn('Still to do by hand:');
        $this->components->bulletList($steps);
    }
}
