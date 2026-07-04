<?php

namespace App\Console\Commands;

use App\Services\Installer\InstallationStateService;
use Illuminate\Console\Command;

class InstallStatusCommand extends Command
{
    protected $signature = 'hybridcore:install-status';

    protected $description = 'Show the HybridCore installation state and every marker';

    public function handle(InstallationStateService $state): int
    {
        $status = $state->status();

        $this->table(['Marker', 'Value'], [
            ['storage/installed.lock', $status['lock_file'] ? 'present' : 'missing'],
            ['APP_INSTALLED (config)', $status['env_flag'] ? 'true' : 'false/unset'],
            ['settings.installed_at', is_string($status['database_flag']) ? $status['database_flag'] : ($status['database_flag'] ? 'present' : 'missing')],
        ]);

        if ($status['lock_metadata'] !== null) {
            $this->line('Lock metadata:');
            foreach ($status['lock_metadata'] as $key => $value) {
                $this->line("  {$key}: ".(is_scalar($value) ? $value : json_encode($value)));
            }
        }

        $status['installed']
            ? $this->info('Detected state: INSTALLED')
            : $this->warn('Detected state: NOT INSTALLED — /install is open');

        return self::SUCCESS;
    }
}
