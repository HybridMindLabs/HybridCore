<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * The core self-update, shared by Admin > Update and `hybridcore:update`.
 *
 * Every step's exit code is checked, and a failure after the code was pulled
 * puts the previous version back — migrations from this run are rolled back,
 * the checkout is reset and dependencies reinstalled — so a broken update
 * never leaves the site live on a half-applied release.
 *
 * Progress is written to the cache while it runs — the planned steps, the one
 * running now with its live output, durations, a percentage and an estimate
 * based on the previous run — so the panel can show a progress bar even
 * though the update request itself only answers at the very end.
 */
class CoreUpdater
{
    public const PROGRESS_KEY = 'hybridcore.update.progress';

    private const LOCK_KEY = 'hybridcore.update.lock';

    /** How long each step took last time, for the time estimate. */
    private const DURATIONS_KEY = 'hybridcore.update.durations';

    /**
     * Rough share of a typical run per step; refined by real durations once
     * an update has finished on this server.
     */
    private const WEIGHTS = [
        'maintenance on' => 1, 'git fetch' => 4, 'git pull' => 3, 'fix folder permissions' => 1,
        'composer install' => 20, 'database backup' => 6, 'php artisan migrate' => 8,
        'php artisan hybridcore:themes:sync' => 2, 'php artisan hybridcore:build' => 40,
        'restart SSR renderer' => 2, 'php artisan optimize:clear' => 2, 'php artisan queue:restart' => 1,
        'maintenance off' => 1,
    ];

    private const MIN_FREE_BYTES = 512 * 1024 * 1024;

    /** @var array<int, array{step: string, output: string, ok: bool, seconds?: float}> */
    private array $log = [];

    /** @var array<int, string> */
    private array $plan = [];

    /** @var array{step: string, started_at: string, output: string}|null */
    private ?array $current = null;

    private float $stepStartedAt = 0;

    private float $runStartedAt = 0;

    private float $lastFlush = 0;

    /** @var (callable(array<int, array{step: string, output: string, ok: bool}>): void)|null */
    private $onStep = null;

    public function __construct(
        private readonly GitUpdateVerifier $verifier,
        private readonly DatabaseBackupService $backups,
        private readonly ActivityLogService $activity,
    ) {}

    /**
     * Problems that make starting an update unsafe. Empty means go.
     *
     * @return array<int, string>
     */
    public function preflight(bool $pull = true): array
    {
        $problems = [];

        if (! file_exists(storage_path('installed.lock'))) {
            $problems[] = 'This instance is not marked as installed.';
        }

        if ($pull && ! is_dir(base_path('.git'))) {
            $problems[] = 'Not a git installation — replace the files, then run: php artisan hybridcore:update --local';
        }

        if ($pull && is_dir(base_path('.git'))) {
            [, $dirty] = $this->exec(['git', 'status', '--porcelain', '--untracked-files=no']);

            if (trim($dirty) !== '') {
                $files = collect(explode("\n", trim($dirty)))->take(5)->map(fn ($l) => trim(substr($l, 2)))->implode(', ');
                $problems[] = "Tracked files were changed on the server ({$files}). Commit or discard them first, or the pull would fail halfway.";
            }
        }

        $free = @disk_free_space(base_path());
        if ($free !== false && $free < self::MIN_FREE_BYTES) {
            $problems[] = 'Less than 512 MB of disk space is free — composer and the asset build need room.';
        }

        if ($this->isRunning()) {
            $problems[] = 'Another update is already running.';
        }

        return $problems;
    }

    /**
     * @param  (callable(array<int, array{step: string, output: string, ok: bool}>): void)|null  $onStep
     * @return array{success: bool, message: string, log: array<int, array{step: string, output: string, ok: bool}>, from: string, to: string, rolled_back: bool, backup: ?string}
     */
    public function run(bool $pull = true, bool $composer = true, bool $build = true, ?callable $onStep = null): array
    {
        $this->log = [];
        $this->onStep = $onStep;
        $this->plan = $this->plan($pull, $composer, $build);
        $this->current = null;
        $this->runStartedAt = microtime(true);

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (! $lock->get()) {
            return $this->result(false, 'Another update is already running.', '', '', false, null);
        }

        $from = $this->head();
        $pulled = false;
        $batchBefore = $this->latestBatch();
        $backup = null;
        $failure = null;
        $rolledBack = false;

        $this->progress(true);
        $this->begin('maintenance on');
        Artisan::call('down', ['--retry' => 30]);
        $this->record('maintenance on', '', true);

        try {
            if ($pull) {
                $this->step('git fetch', ['git', 'fetch', 'origin']);
                $this->verifier->assertIncomingCommitsAreSigned(base_path());
                $this->step('git pull', ['git', 'pull', '--ff-only']);
                $pulled = $this->head() !== $from;
            }

            // A pull can add folders that don't inherit storage/'s group permissions.
            $this->begin('fix folder permissions');
            [$ok, $out] = $this->exec(['chmod', '-R', 'ug+rwX', storage_path(), base_path('bootstrap/cache')]);
            $this->record('fix folder permissions', trim($out) ?: 'OK', $ok);

            if ($composer) {
                $this->step('composer install', ['composer', 'install', '--no-interaction', '--no-dev', '--optimize-autoloader']);
            }

            $backup = $this->backupDatabase();

            $this->artisanStep('migrate', ['--force' => true]);
            $this->artisanStep('hybridcore:themes:sync');

            if ($build) {
                // Restores the previous bundle itself when the build fails.
                $this->artisanStep('hybridcore:build', ['--sync' => true]);
            }

            if ($build && config('inertia.ssr.enabled')) {
                // Needs a pre-authorised narrow sudo rule; -n fails fast instead of prompting.
                $this->begin('restart SSR renderer');
                [$ok, $out] = $this->exec(['sudo', '-n', 'systemctl', 'restart', 'hybridcore-ssr']);
                $this->record('restart SSR renderer', trim($out) ?: 'Restarted hybridcore-ssr.', $ok);
            }

            $this->artisanStep('optimize:clear');
            $this->artisanStep('queue:restart');
        } catch (Throwable $e) {
            $failure = $e;

            if ($pulled) {
                $rolledBack = $this->rollback($from, $batchBefore, $composer);
            }
        } finally {
            $this->begin('maintenance off');
            Artisan::call('up');
            $this->record('maintenance off', '', true);
            $lock->release();
        }

        $to = $this->head();

        if ($failure !== null) {
            $message = $failure->getMessage().($rolledBack
                ? ' The previous version was restored, the site is running as before.'
                : ($pulled ? ' Rolling back failed — check the log below.' : ''));

            return $this->finish($this->result(false, $message, $from, $to, $rolledBack, $backup));
        }

        $this->activity->log('system.updated', $from === $to ? 'Ran system update (already on the latest code)' : "Updated from {$from} to {$to}", null, [
            'from' => $from,
            'to' => $to,
            'backup' => $backup,
        ]);

        $this->rememberDurations();

        return $this->finish($this->result(true, 'Update complete.', $from, $to, false, $backup));
    }

    /**
     * The steps a run with these options goes through, in order.
     *
     * @return array<int, string>
     */
    public function plan(bool $pull = true, bool $composer = true, bool $build = true): array
    {
        return array_values(array_filter([
            'maintenance on',
            $pull ? 'git fetch' : null,
            $pull ? 'git pull' : null,
            'fix folder permissions',
            $composer ? 'composer install' : null,
            'database backup',
            'php artisan migrate',
            'php artisan hybridcore:themes:sync',
            $build ? 'php artisan hybridcore:build' : null,
            $build && config('inertia.ssr.enabled') ? 'restart SSR renderer' : null,
            'php artisan optimize:clear',
            'php artisan queue:restart',
            'maintenance off',
        ]));
    }

    /** A run that stopped reporting for 20 minutes was killed (timeout, OOM) and no longer counts. */
    public function isRunning(): bool
    {
        $progress = Cache::get(self::PROGRESS_KEY);

        return ($progress['running'] ?? false) === true
            && now()->diffInMinutes($progress['updated_at'] ?? now(), absolute: true) < 20;
    }

    /** Undo a failed run: its migrations first (their down() lives in the new code), then the code. */
    private function rollback(string $from, int $batchBefore, bool $composer): bool
    {
        try {
            if ($this->latestBatch() > $batchBefore) {
                $this->artisanStep('migrate:rollback', ['--force' => true]);
            }

            $this->step('git reset to previous version', ['git', 'reset', '--hard', $from]);

            if ($composer) {
                $this->step('composer install (previous version)', ['composer', 'install', '--no-interaction', '--no-dev', '--optimize-autoloader']);
            }

            $this->artisanStep('optimize:clear');

            return $this->head() === $from;
        } catch (Throwable $e) {
            $this->record('rollback', $e->getMessage(), false);

            return false;
        }
    }

    private function backupDatabase(): ?string
    {
        $this->begin('database backup');

        if (DB::connection()->getDriverName() !== 'mysql' && DB::connection()->getDriverName() !== 'mariadb') {
            $this->record('database backup', 'Skipped — only MySQL/MariaDB databases are backed up automatically.', true);

            return null;
        }

        $result = $this->backups->create();
        $this->record('database backup', $result['ok'] ? 'Saved '.$result['filename'] : $result['error'], $result['ok']);

        return $result['ok'] ? $result['filename'] : null;
    }

    private function latestBatch(): int
    {
        try {
            return (int) DB::table('migrations')->max('batch');
        } catch (Throwable) {
            return 0;
        }
    }

    protected function head(): string
    {
        [, $out] = $this->exec(['git', 'rev-parse', '--short', 'HEAD']);

        return trim($out);
    }

    /**
     * @param  array<int, string>  $command
     * @return array{0: bool, 1: string}
     */
    protected function exec(array $command): array
    {
        $process = new Process($command, base_path(), null, null, 900);
        // Stream output into the running step so the panel shows composer
        // and npm working instead of a frozen spinner.
        $process->run(fn (string $type, string $chunk) => $this->live($chunk));

        return [$process->isSuccessful(), $process->getOutput().$process->getErrorOutput()];
    }

    /** @param  array<int, string>  $command */
    private function step(string $label, array $command): void
    {
        $this->begin($label);
        [$ok, $out] = $this->exec($command);
        $this->record($label, trim($out), $ok);

        if (! $ok) {
            throw new RuntimeException("{$label} failed.");
        }
    }

    /** @param  array<string, mixed>  $arguments */
    protected function artisanStep(string $command, array $arguments = []): void
    {
        $this->begin("php artisan {$command}");

        $updater = $this;
        $output = new class($updater) extends BufferedOutput
        {
            public function __construct(private readonly CoreUpdater $updater)
            {
                parent::__construct();
            }

            protected function doWrite(string $message, bool $newline): void
            {
                parent::doWrite($message, $newline);
                $this->updater->live($message.($newline ? "\n" : ''));
            }
        };

        $exit = Artisan::call($command, $arguments, $output);
        $this->record("php artisan {$command}", trim($output->fetch()), $exit === 0);

        if ($exit !== 0) {
            throw new RuntimeException("php artisan {$command} failed.");
        }
    }

    /** Mark a step as started, so the panel can show what is running right now. */
    private function begin(string $step): void
    {
        $this->stepStartedAt = microtime(true);
        $this->current = ['step' => $step, 'started_at' => now()->toIso8601String(), 'output' => ''];
        $this->progress(true);
    }

    /**
     * Output arriving while a step runs. Kept to the last few KB and written
     * to the cache at most once a second.
     *
     * @internal called by exec() and the artisan output buffer
     */
    public function live(string $chunk): void
    {
        if ($this->current === null) {
            return;
        }

        $this->current['output'] = mb_substr($this->current['output'].$chunk, -4000);

        if (microtime(true) - $this->lastFlush >= 1) {
            $this->progress(true);
        }
    }

    private function record(string $step, string $output, bool $ok): void
    {
        $seconds = $this->current !== null && $this->current['step'] === $step
            ? round(microtime(true) - $this->stepStartedAt, 1)
            : 0.0;

        $this->log[] = ['step' => $step, 'output' => mb_substr($output, 0, 8000), 'ok' => $ok, 'seconds' => $seconds];
        $this->current = null;
        $this->progress(true);

        if ($this->onStep) {
            ($this->onStep)($this->log);
        }
    }

    /** Lets the panel follow a long update even if its own request times out. */
    private function progress(bool $running, ?array $result = null): void
    {
        $this->lastFlush = microtime(true);

        Cache::put(self::PROGRESS_KEY, [
            'running' => $running,
            'plan' => $this->plan,
            'log' => $this->log,
            'current' => $this->current,
            'percent' => $result !== null ? 100 : $this->percent(),
            'eta_seconds' => $result !== null ? 0 : $this->eta(),
            'elapsed_seconds' => $this->runStartedAt ? (int) round(microtime(true) - $this->runStartedAt) : 0,
            'result' => $result,
            'updated_at' => now()->toIso8601String(),
        ], 3600);
    }

    /** Share of the planned work that is done, weighted by how long each step usually takes. */
    private function percent(): int
    {
        $weights = $this->weights();
        $total = array_sum($weights) ?: 1;
        $done = 0.0;

        foreach (array_unique(array_column($this->log, 'step')) as $step) {
            $done += $weights[$step] ?? 0;
        }

        // The step running now counts in proportion to its usual duration, never as finished.
        if ($this->current !== null && isset($weights[$this->current['step']])) {
            $expected = $this->expectedSeconds($this->current['step']);
            $share = $expected > 0 ? min(0.9, (microtime(true) - $this->stepStartedAt) / $expected) : 0.5;
            $done += $weights[$this->current['step']] * $share;
        }

        return (int) min(99, floor($done / $total * 100));
    }

    /** Seconds left, from how long the remaining steps took last time; null before any update has finished here. */
    private function eta(): ?int
    {
        $durations = Cache::get(self::DURATIONS_KEY);
        if (! is_array($durations)) {
            return null;
        }

        $finished = array_flip(array_column($this->log, 'step'));
        $left = 0.0;
        foreach ($this->plan as $step) {
            if (isset($finished[$step])) {
                continue;
            }
            $left += (float) ($durations[$step] ?? 0);
            if ($this->current !== null && $this->current['step'] === $step) {
                $left -= microtime(true) - $this->stepStartedAt;
            }
        }

        return max(0, (int) round($left));
    }

    /** @return array<string, float> step => weight, only for steps in this run's plan */
    private function weights(): array
    {
        $durations = Cache::get(self::DURATIONS_KEY);

        return collect($this->plan)->mapWithKeys(fn (string $step) => [
            $step => is_array($durations) && isset($durations[$step])
                ? max(0.5, (float) $durations[$step])
                : (float) (self::WEIGHTS[$step] ?? 1),
        ])->all();
    }

    private function expectedSeconds(string $step): float
    {
        $durations = Cache::get(self::DURATIONS_KEY);

        return is_array($durations) && isset($durations[$step]) ? (float) $durations[$step] : (float) (self::WEIGHTS[$step] ?? 1) * 3;
    }

    private function rememberDurations(): void
    {
        Cache::forever(self::DURATIONS_KEY, collect($this->log)
            ->filter(fn (array $e) => in_array($e['step'], $this->plan, true))
            ->mapWithKeys(fn (array $e) => [$e['step'] => $e['seconds'] ?? 0])
            ->all());
    }

    /**
     * @param  array{success: bool, message: string, log: array<int, array{step: string, output: string, ok: bool}>, from: string, to: string, rolled_back: bool, backup: ?string}  $result
     * @return array{success: bool, message: string, log: array<int, array{step: string, output: string, ok: bool}>, from: string, to: string, rolled_back: bool, backup: ?string}
     */
    private function finish(array $result): array
    {
        $this->progress(false, $result);

        return $result;
    }

    /** @return array{success: bool, message: string, log: array<int, array{step: string, output: string, ok: bool}>, from: string, to: string, rolled_back: bool, backup: ?string} */
    private function result(bool $success, string $message, string $from, string $to, bool $rolledBack, ?string $backup): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'log' => $this->log,
            'seconds' => $this->runStartedAt ? (int) round(microtime(true) - $this->runStartedAt) : 0,
            'from' => $from,
            'to' => $to,
            'rolled_back' => $rolledBack,
            'backup' => $backup,
        ];
    }
}
