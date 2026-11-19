<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks that the code on disk actually serves pages: the database and cache
 * answer, and key pages render without a server error. The updater runs it in
 * a fresh process after every update, so a release that installs cleanly but
 * crashes on the first request is rolled back instead of left live.
 */
class SmokeTestCommand extends Command
{
    protected $signature = 'hybridcore:smoke
        {--secret= : Maintenance-mode bypass secret, to test pages while the site is down}';

    protected $description = 'Check that the site boots and its key pages render';

    /** Pages every installation has; a 4xx is fine (login wall), a 5xx is not. */
    private const PAGES = ['/', '/servers', '/login', '/admin/login'];

    public function handle(HttpKernel $kernel): int
    {
        $failures = [];

        try {
            DB::select('select 1');
            $this->line('✓ database answers');
        } catch (Throwable $e) {
            $failures[] = 'database: '.$e->getMessage();
        }

        try {
            $key = 'hybridcore.smoke.'.Str::random(8);
            Cache::put($key, 'ok', 30);
            $ok = Cache::pull($key) === 'ok';
            $ok ? $this->line('✓ cache answers') : $failures[] = 'cache: a value written was not read back';
        } catch (Throwable $e) {
            $failures[] = 'cache: '.$e->getMessage();
        }

        $cookies = $this->option('secret')
            ? ['laravel_maintenance' => MaintenanceModeBypassCookie::create((string) $this->option('secret'))->getValue()]
            : [];

        foreach (self::PAGES as $path) {
            try {
                $request = Request::create($path, 'GET', [], $cookies, [], ['HTTP_ACCEPT' => 'text/html', 'HTTP_USER_AGENT' => 'HybridCoreHealthCheck/1.0 (bot)']);
                $response = $kernel->handle($request);
                $status = $response->getStatusCode();
                $kernel->terminate($request, $response);

                if ($status >= 500) {
                    $failures[] = "{$path} answered {$status}";
                } else {
                    $this->line("✓ {$path} answered {$status}");
                }
            } catch (Throwable $e) {
                $failures[] = "{$path}: ".$e->getMessage();
            }
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error('✗ '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('The site works.');

        return self::SUCCESS;
    }
}
