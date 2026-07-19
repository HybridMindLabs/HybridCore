<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\UpdateController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Safe, one-shot core update for self-hosted installations.
 *
 *   php artisan hybridcore:update           # git-based install: pulls + upgrades
 *   php artisan hybridcore:update --local   # ZIP-based install: files were already
 *                                           # replaced by hand, run the upgrade steps only
 *
 * Sequence: maintenance on → (git pull) → composer install → migrate →
 * caches cleared → queue restarted → maintenance off. Maintenance mode is
 * always lifted again, even when a step fails.
 */
class CoreUpdateCommand extends Command
{
    protected $signature = 'hybridcore:update
        {--local : Skip git pull — files were already replaced (ZIP install)}
        {--no-composer : Skip composer install (vendor/ shipped with the release)}';

    protected $description = 'Update the HybridCore installation to the latest version';

    public function handle(): int
    {
        $this->components->info('Starting HybridCore update…');

        Artisan::call('down', ['--retry' => 30]);
        $this->components->task('Maintenance mode enabled', fn () => true);

        $failed = null;

        try {
            if (! $this->option('local')) {
                if (! is_dir(base_path('.git'))) {
                    $this->components->error('This is not a git installation. Replace the files manually, then run: php artisan hybridcore:update --local');

                    return self::FAILURE;
                }

                $this->step('git pull', ['git', 'pull', '--ff-only']);
            }

            if (! $this->option('no-composer')) {
                $this->step('composer install', [
                    'composer', 'install', '--no-interaction', '--no-dev', '--optimize-autoloader',
                ]);
            }

            $this->artisanStep('migrate', ['--force' => true]);
            $this->artisanStep('optimize:clear');
            $this->artisanStep('queue:restart');
        } catch (\Throwable $e) {
            $failed = $e;
        } finally {
            Artisan::call('up');
            $this->components->task('Maintenance mode lifted', fn () => true);
        }

        if ($failed !== null) {
            $this->components->error('Update failed: '.$failed->getMessage());
            $this->components->warn('The site is back online but may be in a mixed state — review the output above.');

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

    /** @param array<int, string> $command */
    private function step(string $label, array $command): void
    {
        $process = new Process($command, base_path(), null, null, 600);
        $process->run();

        $this->line("<comment>{$label}</comment>");
        $this->line(trim($process->getOutput().$process->getErrorOutput()));

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("{$label} failed");
        }
    }

    /** @param array<string, mixed> $arguments */
    private function artisanStep(string $command, array $arguments = []): void
    {
        Artisan::call($command, $arguments);
        $this->line("<comment>php artisan {$command}</comment>");
        $this->line(trim(Artisan::output()));
    }
}
