<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The command emits a config file that someone pastes into /etc verbatim, so
 * the exact text matters: a unit that reads nicely in a terminal but fails to
 * parse is worse than no unit at all.
 */
class SystemdCommandTest extends TestCase
{
    private function unitFiles(string $user = 'hcuser'): string
    {
        Artisan::call('hybridcore:systemd', ['--user' => $user]);

        return Artisan::output();
    }

    public function test_unit_directives_start_at_the_beginning_of_the_line(): void
    {
        $output = $this->unitFiles();

        // systemd is not reliably forgiving about leading whitespace, and the
        // whole point is that these lines can be copied straight out.
        foreach (['[Unit]', '[Service]', '[Install]', 'ExecStart=', 'WantedBy=', 'Restart='] as $directive) {
            $this->assertMatchesRegularExpression(
                '/^'.preg_quote($directive, '/').'/m',
                $output,
                "[$directive] must sit at the start of its line to paste into a unit file.",
            );
        }
    }

    public function test_it_uses_the_requested_user_and_this_projects_path(): void
    {
        $output = $this->unitFiles('privateserv');

        $this->assertStringContainsString('User=privateserv', $output);
        $this->assertStringContainsString('WorkingDirectory='.base_path(), $output);
    }

    public function test_it_defines_both_background_processes(): void
    {
        $output = $this->unitFiles();

        // The scheduler runs in the foreground on a loop, replacing cron.
        $this->assertStringContainsString('artisan schedule:work', $output);
        $this->assertStringContainsString('artisan queue:work', $output);
        $this->assertStringContainsString('hybridcore-scheduler', $output);
        $this->assertStringContainsString('hybridcore-worker', $output);
    }

    public function test_the_services_restart_and_the_worker_is_given_time_to_finish(): void
    {
        $output = $this->unitFiles();

        $this->assertStringContainsString('Restart=always', $output);
        // Without this systemd would kill a job mid-flight on restart.
        $this->assertStringContainsString('TimeoutStopSec=', $output);
    }

    public function test_it_never_suggests_running_php_as_root(): void
    {
        $output = $this->unitFiles();

        $this->assertStringNotContainsString('User=root', $output);
        // Root-owned files under storage/ are how this project broke twice.
        $this->assertStringContainsString('Do not run these as root', $output);
    }
}
