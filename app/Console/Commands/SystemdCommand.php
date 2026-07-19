<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prints ready-to-use systemd units for HybridCore's background processes: the
 * scheduler and queue worker, which every install needs, plus the websocket
 * server and the SSR renderer, which are only printed when they are actually
 * switched on.
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
                            {--user= : The user the services run as (default: the current one)}
                            {--all : Print every unit, including ones this install has switched off}';

    protected $description = 'Print systemd unit files for the background services';

    public function handle(): int
    {
        $user = (string) ($this->option('user') ?: $this->currentUser());
        $php = (string) (PHP_BINARY ?: 'php');
        $root = base_path();
        $all = (bool) $this->option('all');

        if (str_contains($php, 'php-fpm')) {
            // PHP_BINARY under FPM points at the FPM daemon, which cannot run
            // artisan. The CLI binary sits beside it often enough to guess.
            $php = str_replace('php-fpm', 'php', $php);
        }

        $reverbOn = config('broadcasting.default') === 'reverb';
        $ssrOn = (bool) config('inertia.ssr.enabled');

        /** @var array<int, array{name: string, unit: string}> $units */
        $units = [
            ['name' => 'hybridcore-scheduler', 'unit' => $this->schedulerUnit($user, $php, $root)],
            ['name' => 'hybridcore-worker', 'unit' => $this->workerUnit($user, $php, $root)],
        ];

        if ($reverbOn || $all) {
            $units[] = ['name' => 'hybridcore-reverb', 'unit' => $this->reverbUnit($user, $php, $root)];
        }

        if ($ssrOn || $all) {
            $units[] = ['name' => 'hybridcore-ssr', 'unit' => $this->ssrUnit($user, $php, $root)];
        }

        $this->components->info('systemd units for HybridCore');

        $this->line('');
        $this->components->twoColumnDetail('<fg=gray>User</>', $user);
        $this->components->twoColumnDetail('<fg=gray>Directory</>', $root);
        $this->components->twoColumnDetail('<fg=gray>PHP</>', $php);
        $this->components->twoColumnDetail('<fg=gray>Websockets (Reverb)</>', $reverbOn ? 'enabled' : 'off');
        $this->components->twoColumnDetail('<fg=gray>Server-side rendering</>', $ssrOn ? 'enabled' : 'off');
        $this->line('');

        if (! $all && (! $reverbOn || ! $ssrOn)) {
            $this->components->bulletList([
                'Units for the disabled services are not printed — run with --all to see them anyway.',
            ]);
            $this->line('');
        }

        $step = 1;
        $names = [];

        foreach ($units as $service) {
            $names[] = $service['name'];

            $this->line(sprintf(
                '<fg=yellow>%d.</> Create <fg=white>/etc/systemd/system/%s.service</>',
                $step++,
                $service['name'],
            ));
            $this->line('');
            $this->line($service['unit']);
        }

        $list = implode(' ', $names);

        $this->line("<fg=yellow>{$step}.</> Enable and start them");
        $step++;
        $this->line('');
        $this->line(<<<SH
  sudo systemctl daemon-reload
  sudo systemctl enable --now {$list}

SH);

        $this->line("<fg=yellow>{$step}.</> Check they are running");
        $this->line('');
        $this->line(<<<SH
  systemctl status {$list}
  journalctl -u hybridcore-worker -f

SH);

        $notes = [
            'Admin → Health shows a heartbeat for the scheduler and worker within a minute of starting.',
            'After deploying new code: sudo systemctl restart hybridcore-worker (workers hold old code in memory).',
        ];

        if ($ssrOn || $all) {
            $notes[] = 'Restart hybridcore-ssr on every deploy too — it holds the built bundle in memory, and when it is down pages still render, just more slowly and with no error anywhere.';
        }

        $this->components->bulletList($notes);

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

    private function reverbUnit(string $user, string $php, string $root): string
    {
        $host = (string) config('reverb.servers.reverb.host', '0.0.0.0');
        $port = (string) config('reverb.servers.reverb.port', 8080);

        return <<<UNIT
[Unit]
Description=HybridCore websocket server
After=network.target mysql.service

[Service]
Type=simple
User={$user}
WorkingDirectory={$root}
ExecStart={$php} artisan reverb:start --host={$host} --port={$port}
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

UNIT;
    }

    private function ssrUnit(string $user, string $php, string $root): string
    {
        $runtime = $this->resolveRuntime();

        // The command spawns the configured runtime against the built bundle.
        // systemd hands a service a far barer PATH than a login shell, so a
        // node installed through nvm is simply not found — hence the absolute
        // path in Environment rather than trusting a bare `node`.
        $env = str_contains($runtime, '/')
            ? "Environment=INERTIA_SSR_RUNTIME={$runtime}\n"
            : '';

        return <<<UNIT
[Unit]
Description=HybridCore server-side rendering
After=network.target

[Service]
Type=simple
User={$user}
WorkingDirectory={$root}
{$env}ExecStart={$php} artisan inertia:start-ssr
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

UNIT;
    }

    /**
     * The absolute path to the SSR runtime where it can be found, so the unit
     * does not depend on systemd's PATH. Falls back to whatever is configured,
     * which then has to be on the PATH to work.
     */
    private function resolveRuntime(): string
    {
        $configured = (string) config('inertia.ssr.runtime', 'node');

        if (str_contains($configured, '/')) {
            return $configured;
        }

        $found = @shell_exec('command -v '.escapeshellarg($configured).' 2>/dev/null');
        $found = is_string($found) ? trim($found) : '';

        return $found !== '' ? $found : $configured;
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
