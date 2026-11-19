<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Starts `php artisan hybridcore:update` as a process of its own, detached
 * from the web request that asked for it. A PHP-FPM time limit, a proxy
 * timeout or the browser closing can then no longer cut an update off
 * halfway; the panel only follows the progress it writes.
 */
class UpdateLauncher
{
    /**
     * @param  array<int, string>  $arguments  options for hybridcore:update
     * @return bool false when the server does not allow starting processes
     */
    public function launch(array $arguments): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $log = storage_path('logs/update-console.log');
        $command = collect([CoreUpdater::phpBinary(), base_path('artisan'), 'hybridcore:update', ...$arguments])
            ->map(fn (string $part) => escapeshellarg($part))
            ->implode(' ');

        try {
            // nohup + & detaches it, so it outlives this request and its worker.
            $process = Process::fromShellCommandline('nohup '.$command.' > '.escapeshellarg($log).' 2>&1 &', base_path());
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
