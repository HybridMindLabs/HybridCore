<?php

namespace App\Console\Commands;

use App\Jobs\RebuildAssetsJob;
use Illuminate\Console\Command;

class BuildAssetsCommand extends Command
{
    protected $signature = 'hybridcore:build
        {--sync : Run the build synchronously instead of dispatching a queued job}';

    protected $description = 'Rebuild the frontend asset bundle (dispatches a queued job by default)';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Building assets synchronously…');

            $job = new RebuildAssetsJob;
            $job->handle();

            $this->info('Done.');

            return self::SUCCESS;
        }

        RebuildAssetsJob::dispatch()->onQueue('default');

        $this->info('Asset rebuild job queued. Run your queue worker to process it.');
        $this->line('  php artisan queue:work');

        return self::SUCCESS;
    }
}
