<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prints ready-to-use systemd units for the two background processes
 * HybridCore needs: the scheduler and the queue worker.
 *
 * The paths and the user are read from the running process rather than left as
 * placeholders, because a unit file with the wrong user is the single easiest
 * way to end up with root-owned files in storage/ — which breaks the site in a
 * way that is genuinely hard to diagnose.
 *
 * It deliberately only prints. Installing the units needs root, and this command
 * must never be the reason someone runs artisan under sudo.
 */
class SystemdCommand extends Command
{
    protected $signature = 'hybridcore:systemd
                            {--user= : The user the services run as (default: the current one)}';

    protected $description = 'Print systemd unit files for the scheduler and queue worker';

    public function handle(): int
    {
        $user = (string) ($this->option('user') ?: $this->currentUser());
        $php = (string) (PHP_BINARY ?: 'php');
        $root = base_path();

        if (str_contains($php, 'php-fpm')) {
            // PHP_BINARY under FPM points at the FPM daemon, which cannot run
            // artisan. The CLI binary sits beside it often enough to guess.
            $php = str_replace('php-fpm', 'php', $php);
        }

        $this->components->info('systemd units for HybridCore');

        $this->line('');
        $this->components->twoColumnDetail('<fg=gray>User</>', $user);
        $this->components->twoColumnDetail('<fg=gray>Directory</>', $root);
        $this->components->twoColumnDetail('<fg=gray>PHP</>', $php);
        $this->line('');

        $this->line('<fg=yellow>1.</> Create <fg=white>/etc/systemd/system/hybridcore-scheduler.service</>');
        $this->line('');
        $this->line($this->schedulerUnit($user, $php, $root));

        $this->line('<fg=yellow>2.</> Create <fg=white>/etc/systemd/system/hybridcore-worker.service</>');
        $this->line('');
        $this->line($this->workerUnit($user, $php, $root));

        $this->line('<fg=yellow>3.</> Enable and start both');
        $this->line('');
        $this->line(<<<'SH'
  sudo systemctl daemon-reload
  sudo systemctl enable --now hybridcore-scheduler hybridcore-worker

SH);

        $this->line('<fg=yellow>4.</> Check they are running');
        $this->line('');
        $this->line(<<<'SH'
  systemctl status hybridcore-scheduler hybridcore-worker
  journalctl -u hybridcore-worker -f

SH);

        $this->components->bulletList([
            'Admin → Health shows a heartbeat for both within a minute of starting.',
            'After deploying new code: sudo systemctl restart hybridcore-worker (workers hold old code in memory).',
        ]);

        $this->newLine();
        $this->components->warn('Do not run these as root — files written to storage/ would stop being writable by the web server.');

        return self::SUCCESS;
    }

    private function schedulerUnit(string $user, string $php, string $root): string
    {
        // schedule:work is a foreground loop that fires the scheduler every
        // minute, so systemd replaces cron outright — one supervised service
        // instead of a crontab entry that fails silently.
        //
        // Not indented: these lines are meant to be pasted into a unit file
        // verbatim, and systemd is not reliably forgiving about leading space.
        return <<<UNIT
[Unit]
Description=HybridCore scheduler
After=network.target mysql.service

[Service]
Type=simple
User={$user}
WorkingDirectory={$root}
ExecStart={$php} artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

UNIT;
    }

    private function workerUnit(string $user, string $php, string $root): string
    {
        // --max-time recycles the process hourly so a slow leak in any job can
        // never accumulate; systemd starts a fresh one immediately.
        return <<<UNIT
[Unit]
Description=HybridCore queue worker
After=network.target mysql.service

[Service]
Type=simple
User={$user}
WorkingDirectory={$root}
ExecStart={$php} artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5
# Let a running job finish before systemd kills the process on stop/restart.
KillSignal=SIGTERM
TimeoutStopSec=90

[Install]
WantedBy=multi-user.target

UNIT;
    }

    private function currentUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && isset($info['name'])) {
                return (string) $info['name'];
            }
        }

        return get_current_user() ?: 'www-data';
    }
}
