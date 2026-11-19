<?php

namespace App\Services;

use App\Services\Update\UpdateProgress;
use App\Services\Update\UpdateReporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\PhpExecutableFinder;
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
 * Live progress for the panel lives in UpdateProgress; the log file,
 * notifications and webhooks at the end in UpdateReporter.
 */
class CoreUpdater
{
    public const PROGRESS_KEY = UpdateProgress::KEY;

    private const LOCK_KEY = 'hybridcore.update.lock';

    private const MIN_FREE_BYTES = 512 * 1024 * 1024;

    private const COMPOSER_INSTALL = ['composer', 'install', '--no-interaction', '--no-dev', '--optimize-autoloader'];

    private readonly UpdateProgress $progress;

    private readonly UpdateReporter $reporter;

    public function __construct(
        private readonly GitUpdateVerifier $verifier,
        private readonly DatabaseBackupService $backups,
        ActivityLogService $activity,
        AdminNotifier $notifier,
        WebhookDispatcher $webhooks,
    ) {
        $this->progress = new UpdateProgress;
        $this->reporter = new UpdateReporter($activity, $notifier, $webhooks);
    }

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
            'health check',
            'maintenance off',
        ]));
    }

    /**
     * @param  (callable(array<int, array{step: string, output: string, ok: bool}>): void)|null  $onStep
     * @return array<string, mixed>
     */
    public function run(bool $pull = true, bool $composer = true, bool $build = true, ?callable $onStep = null): array
    {
        $this->progress->start($this->plan($pull, $composer, $build), $onStep);

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (! $lock->get()) {
            return $this->finish($this->result(false, 'Another update is already running.', '', '', false, null));
        }

        $from = $this->head();
        $pulled = false;
        $built = false;
        $batchBefore = $this->latestBatch();
        $backup = null;
        $failure = null;
        $rolledBack = false;

        $secret = $this->maintenanceOn();

        try {
            if ($pull) {
                $this->step('git fetch', ['git', 'fetch', 'origin']);
                $this->verifier->assertIncomingCommitsAreSigned(base_path());
                $this->step('git pull', ['git', 'pull', '--ff-only']);
                $pulled = $this->head() !== $from;
            }

            $this->fixPermissions();

            if ($composer) {
                $this->step('composer install', self::COMPOSER_INSTALL);
            }

            $backup = $this->backupDatabase();

            $this->artisanStep('migrate', ['--force' => true]);
            $this->artisanStep('hybridcore:themes:sync');

            if ($build) {
                // Restores the previous bundle itself when the build fails.
                $this->artisanStep('hybridcore:build', ['--sync' => true]);
                $built = true;
                $this->restartSsr();
            }

            $this->artisanStep('optimize:clear');
            $this->artisanStep('queue:restart');

            // Every step passing is not the same as the site working.
            $this->healthCheck($secret);
        } catch (Throwable $e) {
            $failure = $e;

            if ($pulled) {
                $rolledBack = $this->rollback($from, $batchBefore, $composer, $built);
            }
        } finally {
            $this->maintenanceOff();
            $lock->release();
        }

        $to = $this->head();
        $properties = ['from' => $from, 'to' => $to, 'backup' => $backup, 'batch_before' => $batchBefore];

        if ($failure !== null) {
            $message = $failure->getMessage().($rolledBack
                ? ' The previous version was restored, the site is running as before.'
                : ($pulled ? ' Rolling back failed — check the log below.' : ''));

            return $this->conclude('system.update_failed', "System update failed: {$failure->getMessage()}",
                $this->result(false, $message, $from, $to, $rolledBack, $backup), $properties);
        }

        $this->progress->rememberDurations();

        return $this->conclude('system.updated', $from === $to ? 'Ran system update (already on the latest code)' : "Updated from {$from} to {$to}",
            $this->result(true, 'Update complete.', $from, $to, false, $backup), $properties);
    }

    /** @return array<int, string> */
    public function revertPlan(bool $restoreDatabase = false): array
    {
        return array_values(array_filter([
            'maintenance on',
            $restoreDatabase ? 'restore database backup' : 'roll back database changes',
            'git reset to previous version',
            'fix folder permissions',
            'composer install',
            'php artisan hybridcore:build',
            config('inertia.ssr.enabled') ? 'restart SSR renderer' : null,
            'php artisan optimize:clear',
            'php artisan queue:restart',
            'health check',
            'maintenance off',
        ]));
    }

    /**
     * Go back to the version a recorded update started from: this update's
     * migrations are rolled back (or the database backup taken before it is
     * restored), then the old code, packages and site files come back.
     *
     * @param  array{from: string, to: string, backup?: ?string, batch_before?: int}  $update
     * @return array<string, mixed>
     */
    public function revert(array $update, bool $restoreDatabase = false, ?callable $onStep = null): array
    {
        $this->progress->start($this->revertPlan($restoreDatabase), $onStep);

        // The commit goes straight to `git reset --hard`; anything but a plain
        // hash (an option such as --foo, a branch name) is refused.
        if (! preg_match('/^[0-9a-f]{7,40}$/', (string) ($update['from'] ?? ''))) {
            return $this->finish($this->result(false, 'This update has no valid version to go back to.', '', '', false, null));
        }

        $lock = Cache::lock(self::LOCK_KEY, 3600);
        if (! $lock->get()) {
            return $this->finish($this->result(false, 'Another update is already running.', '', '', false, null));
        }

        $from = $this->head();
        $failure = null;
        $secret = $this->maintenanceOn();

        try {
            if ($restoreDatabase) {
                $this->begin('restore database backup');
                $restored = $this->backups->restore((string) ($update['backup'] ?? ''));
                $this->record('restore database backup', $restored['ok'] ? 'Restored '.$update['backup'] : $restored['error'], $restored['ok']);
                if (! $restored['ok']) {
                    throw new RuntimeException('Restoring the database backup failed.');
                }
            } else {
                // Migrations first: their down() only exists in the newer code.
                $this->begin('roll back database changes');
                $target = (int) ($update['batch_before'] ?? $this->latestBatch());
                $rounds = 0;
                while ($this->latestBatch() > $target && $rounds++ < 20) {
                    Artisan::call('migrate:rollback', ['--force' => true]);
                }
                $this->record('roll back database changes', $rounds ? "Rolled back {$rounds} migration batch(es)." : 'Nothing to roll back.', $this->latestBatch() <= $target);
            }

            $this->step('git reset to previous version', ['git', 'reset', '--hard', $update['from']]);
            $this->fixPermissions();
            $this->step('composer install', self::COMPOSER_INSTALL);
            $this->artisanStep('hybridcore:build', ['--sync' => true]);
            $this->restartSsr();
            $this->artisanStep('optimize:clear');
            $this->artisanStep('queue:restart');
            $this->healthCheck($secret);
        } catch (Throwable $e) {
            $failure = $e;
        } finally {
            $this->maintenanceOff();
            $lock->release();
        }

        $to = $this->head();
        $properties = ['from' => $from, 'to' => $to, 'reverted_update' => $update['to'], 'database_restored' => $restoreDatabase];

        if ($failure !== null) {
            return $this->conclude('system.update_failed', "Rolling back to {$update['from']} failed: {$failure->getMessage()}",
                $this->result(false, $failure->getMessage().' Check the log below; the site may need attention.', $from, $to, false, null), $properties);
        }

        return $this->conclude('system.reverted', "Rolled back from {$from} to {$to}",
            $this->result(true, "Rolled back to {$to}.", $from, $to, false, null), $properties);
    }

    /**
     * Show the panel an update that was handed to a background process and
     * has not reported yet, so nobody starts a second one meanwhile.
     *
     * @param  array<int, string>  $plan
     */
    public function markQueued(array $plan): void
    {
        $this->progress->queued($plan);
    }

    /** A run that stopped reporting for 20 minutes was killed (timeout, OOM) and no longer counts. */
    public function isRunning(): bool
    {
        return UpdateProgress::isRunning();
    }

    /** The php CLI binary — under PHP-FPM, PHP_BINARY points at php-fpm itself. */
    public static function phpBinary(): string
    {
        $configured = config('hybridcore.php_binary');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $found = (new PhpExecutableFinder)->find(false);

        return $found && ! str_contains(basename($found), 'fpm') ? $found : 'php';
    }

    /**
     * Output arriving while a step runs.
     *
     * @internal called by exec() and the artisan output buffer
     */
    public function live(string $chunk): void
    {
        $this->progress->live($chunk);
    }

    // ── Steps ────────────────────────────────────────────────────────

    /** Maintenance mode with a one-off bypass secret, so the health check can reach the site. */
    private function maintenanceOn(): string
    {
        $secret = Str::random(40);

        $this->progress->flush(true);
        $this->begin('maintenance on');
        Artisan::call('down', ['--retry' => 30, '--secret' => $secret]);
        $this->record('maintenance on', '', true);

        return $secret;
    }

    private function maintenanceOff(): void
    {
        $this->begin('maintenance off');
        Artisan::call('up');
        $this->record('maintenance off', '', true);
    }

    private function fixPermissions(): void
    {
        // A pull can add folders that don't inherit storage/'s group permissions.
        $this->begin('fix folder permissions');
        [$ok, $out] = $this->exec(['chmod', '-R', 'ug+rwX', storage_path(), base_path('bootstrap/cache')]);
        $this->record('fix folder permissions', trim($out) ?: 'OK', $ok);
    }

    private function restartSsr(): void
    {
        if (! config('inertia.ssr.enabled')) {
            return;
        }

        // Needs a pre-authorised narrow sudo rule; -n fails fast instead of prompting.
        $this->begin('restart SSR renderer');
        [$ok, $out] = $this->exec(['sudo', '-n', 'systemctl', 'restart', 'hybridcore-ssr']);
        $this->record('restart SSR renderer', trim($out) ?: 'Restarted hybridcore-ssr.', $ok);
    }

    /**
     * Boot the new code in a fresh process and request key pages through it.
     * A crash or a 5xx fails the update, which then rolls back.
     */
    private function healthCheck(string $secret): void
    {
        $this->step('health check', [self::phpBinary(), 'artisan', 'hybridcore:smoke', '--secret='.$secret]);
    }

    private function backupDatabase(): ?string
    {
        $this->begin('database backup');

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->record('database backup', 'Skipped — only MySQL/MariaDB databases are backed up automatically.', true);

            return null;
        }

        $result = $this->backups->create();
        $this->record('database backup', $result['ok'] ? 'Saved '.$result['filename'] : $result['error'], $result['ok']);

        return $result['ok'] ? $result['filename'] : null;
    }

    /** Undo a failed run: its migrations first (their down() lives in the new code), then the code. */
    private function rollback(string $from, int $batchBefore, bool $composer, bool $built = false): bool
    {
        try {
            if ($this->latestBatch() > $batchBefore) {
                $this->artisanStep('migrate:rollback', ['--force' => true]);
            }

            $this->step('git reset to previous version', ['git', 'reset', '--hard', $from]);

            if ($composer) {
                $this->step('composer install (previous version)', self::COMPOSER_INSTALL);
            }

            // The new site files were already built; the old code needs its own.
            if ($built) {
                $this->artisanStep('hybridcore:build', ['--sync' => true]);
            }

            $this->artisanStep('optimize:clear');

            return $this->head() === $from;
        } catch (Throwable $e) {
            $this->record('rollback', $e->getMessage(), false);

            return false;
        }
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

        $output = new class($this) extends BufferedOutput
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

    private function begin(string $step): void
    {
        $this->progress->begin($step);
    }

    private function record(string $step, string $output, bool $ok): void
    {
        $this->progress->record($step, $output, $ok);
    }

    // ── Result ───────────────────────────────────────────────────────

    /**
     * Keep the full log, record the outcome and tell the staff.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function conclude(string $event, string $description, array $result, array $properties): array
    {
        $result['log_file'] = $this->reporter->report($event, $description, $result, $properties);

        return $this->finish($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function finish(array $result): array
    {
        $this->progress->flush(false, $result);

        return $result;
    }

    /** @return array{success: bool, message: string, log: array<int, array{step: string, output: string, ok: bool}>, seconds: int, from: string, to: string, rolled_back: bool, backup: ?string} */
    private function result(bool $success, string $message, string $from, string $to, bool $rolledBack, ?string $backup): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'log' => $this->progress->log(),
            'seconds' => $this->progress->elapsedSeconds(),
            'from' => $from,
            'to' => $to,
            'rolled_back' => $rolledBack,
            'backup' => $backup,
        ];
    }
}
