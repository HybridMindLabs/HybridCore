<?php

namespace App\Console\Commands;

use App\Services\Extensions\ExtensionManager;
use Illuminate\Console\Command;

class ExtensionsSyncCommand extends Command
{
    protected $signature = 'hybridcore:extensions:sync';

    protected $description = 'Discover extensions on disk and sync them to the database';

    public function handle(ExtensionManager $manager): int
    {
        $count = $manager->sync();

        $this->info("Synced {$count} extension(s) from disk.");

        return self::SUCCESS;
    }
}
