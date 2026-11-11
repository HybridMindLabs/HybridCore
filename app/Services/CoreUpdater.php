<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * The core self-update, shared by Admin > Update and `hybridcore:update`.
 *
 * Every step's exit code is checked, and a failure after the code was pulled
 * puts the previous version back — migrations from this run are rolled back,
 * the checkout is reset and dependencies reinstalled — so a broken update
 * never leaves the site live on a half-applied release.
 */
class CoreUpdater
{
    public const PROGRESS_KEY = 'hybridcore.update.progress';

    private const LOCK_KEY = 'hybridcore.update.lock';

    private const MIN_FREE_BYTES = 512 * 1024 * 1024;

    /** @var array<int, array{step: string, output: string, ok: bool}> */
    private array $log = [];

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

        return $this->finish($this->result(true, 'Update complete.', $from, $to, false, $backup));
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
        $process->run();

        return [$process->isSuccessful(), $process->getOutput().$process->getErrorOutput()];
    }

    /** @param  array<int, string>  $command */
    private function step(string $label, array $command): void
    {
        [$ok, $out] = $this->exec($command);
        $this->record($label, trim($out), $ok);

        if (! $ok) {
            throw new RuntimeException("{$label} failed.");
        }
    }

    /** @param  array<string, mixed>  $arguments */
    protected function artisanStep(string $command, array $arguments = []): void
    {
        $exit = Artisan::call($command, $arguments);
        $this->record("php artisan {$command}", trim(Artisan::output()), $exit === 0);

        if ($exit !== 0) {
            throw new RuntimeException("php artisan {$command} failed.");
        }
    }

    private function record(string $step, string $output, bool $ok): void
    {
        $this->log[] = ['step' => $step, 'output' => mb_substr($output, 0, 8000), 'ok' => $ok];
        $this->progress(true);

        if ($this->onStep) {
            ($this->onStep)($this->log);
        }
    }

    /** Lets the panel follow a long update even if its own request times out. */
    private function progress(bool $running, ?array $result = null): void
    {
        Cache::put(self::PROGRESS_KEY, [
            'running' => $running,
            'log' => $this->log,
            'result' => $result,
            'updated_at' => now()->toIso8601String(),
        ], 3600);
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
            'from' => $from,
            'to' => $to,
            'rolled_back' => $rolledBack,
            'backup' => $backup,
        ];
    }
}
