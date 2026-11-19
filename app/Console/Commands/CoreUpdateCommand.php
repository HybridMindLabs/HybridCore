<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\UpdateController;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\CoreUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

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
        {--no-composer : Skip composer install (vendor/ shipped with the release)}
        {--build : Also rebuild the site files (the panel always does)}
        {--revert= : Roll back the update recorded under this activity-log id}
        {--restore-database : With --revert, restore the database backup instead of rolling migrations back}
        {--panel : Started from Admin > System Update; the panel already ran the checks}
        {--user= : Admin who started it from the panel, for the activity log}';

    protected $description = 'Update the HybridCore installation to the latest version';

    public function handle(CoreUpdater $updater): int
    {
        if ($this->option('user') && ($user = User::find($this->option('user')))) {
            Auth::setUser($user);
        }

        if ($this->option('revert')) {
            return $this->revert($updater);
        }

        $pull = ! $this->option('local');
        $build = (bool) $this->option('build');

        // From the panel the checks already ran, and its "queued" marker would
        // otherwise read as another update in progress.
        if (! $this->option('panel')) {
            $problems = $updater->preflight($pull);
            if ($problems !== []) {
                $this->components->error('The update was not started:');
                $this->components->bulletList($problems);

                return self::FAILURE;
            }
        }

        $this->components->info('Starting HybridCore update…');

        $plan = $updater->plan($pull, ! $this->option('no-composer'), $build);
        $result = $updater->run(
            pull: $pull,
            composer: ! $this->option('no-composer'),
            build: $build,
            onStep: $this->printer($plan),
        );

        if (! $result['success']) {
            $this->components->error('Update failed: '.$result['message']);

            return self::FAILURE;
        }

        $this->components->info('Update complete in '.$result['seconds'].'s. Current version: '.UpdateController::VERSION);
        if (! $build) {
            $this->remainingSteps();
        }

        return self::SUCCESS;
    }

    private function revert(CoreUpdater $updater): int
    {
        $entry = ActivityLog::where('event', 'system.updated')->find($this->option('revert'));
        if ($entry === null || empty($entry->properties['from'])) {
            $this->components->error('No recorded update with that id.');

            return self::FAILURE;
        }

        $restore = (bool) $this->option('restore-database');
        $this->components->info("Rolling back to {$entry->properties['from']}…");

        $result = $updater->revert($entry->properties, $restore, $this->printer($updater->revertPlan($restore)));

        if (! $result['success']) {
            $this->components->error('Rollback failed: '.$result['message']);

            return self::FAILURE;
        }

        $this->components->info($result['message'].' ('.$result['seconds'].'s)');

        return self::SUCCESS;
    }

    /**
     * Prints each finished step as "✓ [3/13] composer install (38.6s)".
     *
     * @param  array<int, string>  $plan
     */
    private function printer(array $plan): callable
    {
        $printed = 0;

        return function (array $log) use (&$printed, $plan) {
            foreach (array_slice($log, $printed) as $entry) {
                $position = array_search($entry['step'], $plan, true);
                $counter = $position === false ? '' : '['.($position + 1).'/'.count($plan).'] ';
                $time = isset($entry['seconds']) ? sprintf(' <fg=gray>(%ss)</>', $entry['seconds']) : '';
                $this->line(($entry['ok'] ? '<info>✓</info> ' : '<error>✗</error> ').$counter."<comment>{$entry['step']}</comment>".$time);
                if ($entry['output'] !== '') {
                    $this->line($entry['output']);
                }
            }
            $printed = count($log);
        };
    }

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
