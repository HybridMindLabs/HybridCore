<?php

namespace App\Console\Commands;

use App\Services\Installer\InstallationStateService;
use Illuminate\Console\Command;

class InstallLockCommand extends Command
{
    protected $signature = 'hybridcore:install-lock';

    protected $description = 'Mark HybridCore as installed (writes all installation markers)';

    public function handle(InstallationStateService $state): int
    {
        $state->markInstalled(['source' => 'artisan']);

        $this->info('Installation markers written: lock file, APP_INSTALLED, settings.installed_at (where available).');
        $this->call('hybridcore:install-status');

        return self::SUCCESS;
    }
}
