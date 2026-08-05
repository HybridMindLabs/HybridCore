<?php

namespace Hybridcore\Demo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Hybridcore\Demo\Notifications\DemoTestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): Response
    {
        return Inertia::render('Extensions/hybridcore/demo/Admin/Index', [
            'message' => trans('demo::messages.welcome'),
            'greeting' => $this->settings->get('demo.greeting', 'Hi'),
            'showOnboardingStep' => (bool) $this->settings->get('demo.show_onboarding_step', false),
        ]);
    }

    /** Settings URL: /admin/settings/extensions/demo (registered via $registry->settings()). */
    public function settings(): Response
    {
        return Inertia::render('Extensions/hybridcore/demo/Admin/Settings', [
            'greeting' => $this->settings->get('demo.greeting', 'Hi'),
            'showOnboardingStep' => (bool) $this->settings->get('demo.show_onboarding_step', false),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'greeting' => ['required', 'string', 'max:100'],
            'show_onboarding_step' => ['required', 'boolean'],
        ]);

        $this->settings->setMany([
            'demo.greeting' => $data['greeting'],
            'demo.show_onboarding_step' => $data['show_onboarding_step'] ? '1' : '0',
        ]);

        return back()->with('success', trans('demo::messages.settings_saved'));
    }

    /** Fires the notificationTypes() example end to end — see DemoTestNotification. */
    public function notify(Request $request): RedirectResponse
    {
        $request->user()->notify(new DemoTestNotification);

        return back()->with('success', trans('demo::messages.notif_sent'));
    }
}
