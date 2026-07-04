<?php

namespace App\Console\Commands;

use App\Services\Themes\ThemeManager;
use Illuminate\Console\Command;

class ThemesSyncCommand extends Command
{
    protected $signature = 'hybridcore:themes:sync';

    protected $description = 'Discover themes on disk and sync them to the database';

    public function handle(ThemeManager $manager): int
    {
        $count = $manager->sync();

        $this->info("Synced {$count} theme(s) from disk.");

        return self::SUCCESS;
    }
}
