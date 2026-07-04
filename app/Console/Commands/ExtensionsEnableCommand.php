<?php

namespace App\Console\Commands;

use App\Models\Extension;
use App\Services\Extensions\ExtensionManager;
use Illuminate\Console\Command;

class ExtensionsEnableCommand extends Command
{
    protected $signature = 'hybridcore:extensions:enable {slug : Extension slug (e.g. hybridcore/announcements)}';

    protected $description = 'Enable an extension by slug and queue an asset rebuild';

    public function handle(ExtensionManager $manager): int
    {
        $slug = $this->argument('slug');

        $extension = Extension::where('slug', $slug)->first();

        if (! $extension) {
            $this->error("Extension '{$slug}' not found. Run hybridcore:extensions:sync first.");

            return self::FAILURE;
        }

        if ($extension->enabled) {
            $this->info("{$extension->name} is already enabled.");

            return self::SUCCESS;
        }

        $manager->enable($extension);

        $this->info("{$extension->name} enabled. Asset rebuild queued.");

        return self::SUCCESS;
    }
}
