<?php

namespace App\Console\Commands;

use App\Services\Extensions\ExtensionManager;
use App\Services\Extensions\Registries\ExtensionRegistry;
use Illuminate\Console\Command;

class ExtensionsSyncCommand extends Command
{
    protected $signature = 'hybridcore:extensions:sync';

    protected $description = 'Discover extensions on disk and sync them to the database';

    public function handle(ExtensionManager $manager, ExtensionRegistry $registry): int
    {
        $count = $manager->sync();
        $permissions = $registry->permissions()->sync();

        $this->info("Synced {$count} extension(s) from disk and {$permissions} new or changed permission(s).");

        return self::SUCCESS;
    }
}
