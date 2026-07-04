<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Maintenance mode toggle. Uses the settings-based flag (not artisan down)
 * so the admin panel stays reachable while the public site shows 503.
 */
class MaintenanceController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $activity,
    ) {}

    public function enable(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $this->settings->setMany([
            'maintenance_mode' => '1',
            'maintenance_message' => $data['message'] ?? '',
        ]);

        $this->activity->log('system.maintenance-enabled', 'Maintenance mode enabled');

        return back()->with('success', 'Maintenance mode enabled. The public site now shows the maintenance page.');
    }

    public function disable(): RedirectResponse
    {
        $this->settings->set('maintenance_mode', '0');

        $this->activity->log('system.maintenance-disabled', 'Maintenance mode disabled');

        return back()->with('success', 'Maintenance mode disabled.');
    }
}
