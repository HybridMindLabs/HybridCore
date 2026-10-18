<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use App\Services\SystemHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly SettingsService $settings,
        private readonly SystemHealthService $health,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/System/Health', [
            'checks' => $this->health->runChecks(),
            'checkedAt' => now()->toIso8601String(),
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
}
