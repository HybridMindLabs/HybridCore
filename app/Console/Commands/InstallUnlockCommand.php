<?php

namespace App\Console\Commands;

use App\Services\Installer\InstallationStateService;
use Illuminate\Console\Command;

class InstallUnlockCommand extends Command
{
    protected $signature = 'hybridcore:install-unlock {--force : Confirm re-opening the installer}';

    protected $description = 'Remove all installation markers and re-open the installer (DANGEROUS)';

    public function handle(InstallationStateService $state): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to unlock without --force.');
            $this->line('Unlocking re-opens /install to the public. On a production site this allows anyone to re-run the installer.');
            $this->line('If you really mean it: php artisan hybridcore:install-unlock --force');

            return self::FAILURE;
        }

        $state->unlock(force: true);

        $this->warn('Installation markers removed — /install is now OPEN.');
        $this->call('hybridcore:install-status');

        return self::SUCCESS;
    }
}
