<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Runs an extension's own PHPUnit tests against the core test bootstrap.
 *
 *   php artisan hybridcore:extensions:test hybridcore/demo
 */
class ExtensionsTestCommand extends Command
{
    protected $signature = 'hybridcore:extensions:test
        {id : Extension id in vendor/name format (e.g. hybridcore/demo)}
        {--filter= : Forwarded to PHPUnit --filter}';

    protected $description = "Run an extension's test suite";

    public function handle(): int
    {
        $id = strtolower((string) $this->argument('id'));
        $base = $this->resolveBase($id);

        if ($base === null) {
            $this->error("No extension found for \"{$id}\" under extensions/.");

            return self::FAILURE;
        }

        if (! is_dir($base.'/tests')) {
            $this->warn('This extension has no tests/ directory.');

            return self::SUCCESS;
        }

        $command = [
            base_path('vendor/bin/phpunit'),
            '--bootstrap', base_path('vendor/autoload.php'),
            '--colors=always',
            $base.'/tests',
        ];

        if (is_string($this->option('filter')) && $this->option('filter') !== '') {
            array_splice($command, count($command) - 1, 0, ['--filter', $this->option('filter')]);
        }

        $process = new Process($command, base_path(), null, null, 300);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    private function resolveBase(string $id): ?string
    {
        foreach (glob(base_path('extensions/*/*/extension.json')) ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest) && (($manifest['id'] ?? $manifest['slug'] ?? null) === $id)) {
                return dirname($manifestPath);
            }
        }

        return null;
    }
}
