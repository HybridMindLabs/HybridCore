<?php

namespace App\Console\Commands;

use App\Models\Extension;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Runs migrations for enabled extensions (or one specific extension),
 * keeping extension migrations separate from core migrations.
 */
class ExtensionsMigrateCommand extends Command
{
    protected $signature = 'hybridcore:extensions:migrate
        {--extension= : Only migrate one extension (id/slug, e.g. hybridcore/demo)}
        {--pretend : Show the queries without running them}';

    protected $description = 'Run database migrations for enabled extensions';

    public function handle(): int
    {
        if (! Schema::hasTable('extensions')) {
            $this->error('Extensions table missing — is HybridCore installed?');

            return self::FAILURE;
        }

        $query = Extension::where('enabled', true);

        if ($filter = $this->option('extension')) {
            $query = Extension::query()->where(function ($q) use ($filter): void {
                $q->where('slug', $filter)
                    ->orWhere('slug', str_replace('/', '-', $filter))
                    ->orWhere('path', $filter);
            });
        }

        $extensions = $query->get();

        if ($extensions->isEmpty()) {
            $this->warn($filter
                ? "No extension found matching \"{$filter}\"."
                : 'No enabled extensions.');

            return self::SUCCESS;
        }

        $ran = 0;

        foreach ($extensions as $extension) {
            $dir = $extension->metadata['migrations'] ?? 'database/migrations';

            if (! is_string($dir)) {
                continue;
            }

            $path = base_path('extensions/'.$extension->path.'/'.$dir);
            $real = realpath($path);
            $base = realpath(base_path('extensions/'.$extension->path));

            // Traversal guard + existence check.
            if ($real === false || $base === false || ! str_starts_with($real, $base) || ! is_dir($real)) {
                $this->line("  - {$extension->name}: no migrations directory, skipping");

                continue;
            }

            if (glob($real.'/*.php') === []) {
                $this->line("  - {$extension->name}: no migration files, skipping");

                continue;
            }

            $this->info("Migrating: {$extension->name}");

            $this->call('migrate', [
                '--path' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $real),
                '--force' => true,
                '--pretend' => (bool) $this->option('pretend'),
            ]);

            $ran++;
        }

        $this->info("Done — {$ran} extension(s) migrated.");

        return self::SUCCESS;
    }
}
