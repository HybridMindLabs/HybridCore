<?php

namespace App\Console\Commands;

use App\Services\SystemHealthService;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:health {--json : Output diagnostics as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform core system diagnostics, environment checks and service heartbeats';

    public function handle(SystemHealthService $health): int
    {
        $checks = $health->runChecks();

        if ($this->option('json')) {
            $this->output->writeln((string) json_encode([
                'timestamp' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->info('====================================================');
        $this->info(' HybridCore System Health & Diagnostic Suite');
        $this->info('====================================================');
        $this->line(sprintf('Environment: <comment>%s</comment> | PHP: <comment>%s</comment> | Laravel: <comment>%s</comment>', app()->environment(), PHP_VERSION, app()->version()));
        $this->newLine();

        $rows = [];
        $hasFailures = false;

        foreach ($checks as $check) {
            $statusFormatted = match ($check['status']) {
                'ok' => '<info>PASS</info>',
                'warn' => '<comment>WARN</comment>',
                default => '<error>FAIL</error>',
            };

            if ($check['status'] === 'fail') {
                $hasFailures = true;
            }

            $rows[] = [
                $check['category'],
                $check['label'],
                $statusFormatted,
                $check['detail'],
            ];
        }

        $this->table(['Category', 'Check', 'Status', 'Details'], $rows);

        if ($hasFailures) {
            $this->error('Diagnostics completed with one or more critical failures.');

            return 1;
        }

        $this->info('All core diagnostics passed.');

        return 0;
    }
}
