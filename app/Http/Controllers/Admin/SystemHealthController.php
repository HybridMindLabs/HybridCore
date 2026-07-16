<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SystemHealthController extends Controller
{
    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'curl', 'json'];

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/System/Health', [
            'checks' => $this->checks(),
            'info' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'environment' => app()->environment(),
            ],
            'maintenance' => [
                'enabled' => $this->settings->get('maintenance_mode', '0') === '1',
                'message' => (string) ($this->settings->get('maintenance_message') ?: ''),
            ],
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('cache:clear');
        $this->activity->log('system.cache-cleared', 'Application cache cleared');

        return back()->with('success', 'Application cache cleared.');
    }

    public function clearConfigCache(): RedirectResponse
    {
        Artisan::call('config:clear');
        $this->activity->log('system.config-cleared', 'Config cache cleared');

        return back()->with('success', 'Config cache cleared.');
    }

    public function clearRouteCache(): RedirectResponse
    {
        Artisan::call('route:clear');
        $this->activity->log('system.route-cleared', 'Route cache cleared');

        return back()->with('success', 'Route cache cleared.');
    }

    /** @return array<int, array{label: string, status: string, detail: string}> */
    private function checks(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP Version',
            'status' => version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail',
            'detail' => PHP_VERSION.' (8.3+ required)',
        ];

        $checks[] = [
            'label' => 'Laravel Version',
            'status' => 'ok',
            'detail' => app()->version(),
        ];

        $missing = array_filter(self::REQUIRED_EXTENSIONS, fn (string $ext) => ! extension_loaded($ext) && $ext !== 'json');
        $checks[] = [
            'label' => 'PHP Extensions',
            'status' => $missing === [] ? 'ok' : 'fail',
            'detail' => $missing === [] ? 'All required extensions loaded' : 'Missing: '.implode(', ', $missing),
        ];

        $checks[] = [
            'label' => 'APP_KEY',
            'status' => config('app.key') ? 'ok' : 'fail',
            'detail' => config('app.key') ? 'Set' : 'Missing — run php artisan key:generate',
        ];

        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $dbDetail = DB::connection()->getDatabaseName();
        } catch (Throwable $e) {
            $dbOk = false;
            $dbDetail = 'Connection failed';
        }
        $checks[] = ['label' => 'Database Connection', 'status' => $dbOk ? 'ok' : 'fail', 'detail' => $dbDetail];

        $checks[] = [
            'label' => 'Storage Writable',
            'status' => is_writable(storage_path()) ? 'ok' : 'fail',
            'detail' => storage_path(),
        ];

        $checks[] = [
            'label' => 'Bootstrap Cache Writable',
            'status' => is_writable(base_path('bootstrap/cache')) ? 'ok' : 'fail',
            'detail' => base_path('bootstrap/cache'),
        ];

        $checks[] = [
            'label' => 'Session Driver',
            'status' => config('session.driver') ? 'ok' : 'warn',
            'detail' => (string) config('session.driver'),
        ];

        try {
            Cache::put('hc_health_check', '1', 5);
            $cacheOk = Cache::get('hc_health_check') === '1';
        } catch (Throwable) {
            $cacheOk = false;
        }
        $checks[] = [
            'label' => 'Cache Writable',
            'status' => $cacheOk ? 'ok' : 'fail',
            'detail' => (string) config('cache.default'),
        ];

        $checks[] = [
            'label' => 'Queue Driver',
            'status' => config('queue.default') !== null ? 'ok' : 'warn',
            'detail' => (string) config('queue.default'),
        ];

        $checks[] = $this->heartbeatCheck(
            label: 'Scheduler',
            cacheKey: 'health.scheduler_last_run',
            staleAfterMinutes: 3,
            missingDetail: 'No heartbeat yet — nothing is running the scheduler. Run "php artisan hybridcore:systemd" for a service that does.',
        );

        $checks[] = $this->heartbeatCheck(
            label: 'Queue Worker',
            cacheKey: 'health.queue_worker_last_run',
            staleAfterMinutes: 3,
            missingDetail: 'No heartbeat yet — no worker has processed a job. Run "php artisan hybridcore:systemd" for a service that keeps one running.',
        );

        $checks[] = $this->reverbCheck();

        $checks[] = [
            'label' => 'Mail Configuration',
            'status' => config('mail.default') && config('mail.from.address') ? 'ok' : 'warn',
            'detail' => (string) config('mail.default'),
        ];

        $checks[] = [
            'label' => 'Installation Lock',
            'status' => file_exists(storage_path('installed.lock')) ? 'ok' : 'fail',
            'detail' => file_exists(storage_path('installed.lock')) ? 'Locked' : 'Missing — installer is open!',
        ];

        $checks[] = [
            'label' => 'Environment',
            'status' => app()->environment('production') ? 'ok' : 'warn',
            'detail' => app()->environment(),
        ];

        $checks[] = [
            'label' => 'Debug Mode',
            'status' => config('app.debug') ? 'warn' : 'ok',
            'detail' => config('app.debug') ? 'Enabled — disable in production!' : 'Disabled',
        ];

        return $checks;
    }

    /**
     * Both the scheduler and the queue worker write a heartbeat timestamp to
     * cache every minute (routes/console.php) — if either process isn't
     * actually running, its key goes stale or never appears at all.
     *
     * @return array{label: string, status: string, detail: string}
     */
    private function heartbeatCheck(string $label, string $cacheKey, int $staleAfterMinutes, string $missingDetail): array
    {
        $lastRun = Cache::get($cacheKey);

        if (! $lastRun) {
            return ['label' => $label, 'status' => 'warn', 'detail' => $missingDetail];
        }

        $age = now()->diffInMinutes(Carbon::parse($lastRun));

        return [
            'label' => $label,
            'status' => $age <= $staleAfterMinutes ? 'ok' : 'fail',
            'detail' => $age <= $staleAfterMinutes
                ? 'Last heartbeat '.$age.' min ago'
                : 'Stale — last heartbeat '.$age.' min ago (expected every minute)',
        ];
    }

    /**
     * Opens a raw TCP connection to the configured Reverb host/port — this
     * is the actual websocket process the browser connects to for live
     * notifications, presence, and server stats, separate from the queue
     * worker/scheduler heartbeats above.
     *
     * @return array{label: string, status: string, detail: string}
     */
    private function reverbCheck(): array
    {
        $label = 'Reverb (Websockets)';

        if (config('broadcasting.default') !== 'reverb') {
            return ['label' => $label, 'status' => 'warn', 'detail' => 'Broadcasting driver is not "reverb"'];
        }

        $host = config('broadcasting.connections.reverb.options.host');
        $port = (int) config('broadcasting.connections.reverb.options.port');

        if (! $host || ! $port) {
            return ['label' => $label, 'status' => 'warn', 'detail' => 'REVERB_HOST/REVERB_PORT not configured'];
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, 2);

        if (! $connection) {
            return ['label' => $label, 'status' => 'fail', 'detail' => "Could not reach {$host}:{$port} — {$errstr}"];
        }

        fclose($connection);

        return ['label' => $label, 'status' => 'ok', 'detail' => "Reachable at {$host}:{$port}"];
    }
}
