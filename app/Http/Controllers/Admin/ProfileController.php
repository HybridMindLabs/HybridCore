<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Services\AdminNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Profile/Index', [
            'alerts' => collect(AdminNotifier::ALERTS)
                ->filter(fn (array $a) => $user->hasPermission($a['permission']))
                ->map(fn (array $a, string $key) => [
                    'key' => $key,
                    'label' => $a['label'],
                    'description' => $a['description'],
                    ...AdminNotifier::channelsFor($user, $key),
                ])
                ->values(),
        ]);
    }

    public function updateAlerts(Request $request): RedirectResponse
    {
        $keys = array_keys(AdminNotifier::ALERTS);
        $data = $request->validate([
            'alerts' => ['required', 'array'],
            'alerts.*.key' => ['required', 'string', 'in:'.implode(',', $keys)],
            'alerts.*.database' => ['required', 'boolean'],
            'alerts.*.mail' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $prefs = $user->notification_preferences ?? [];

        foreach ($data['alerts'] as $alert) {
            $prefs['admin_alerts'][$alert['key']] = [
                'database' => (bool) $alert['database'],
                'mail' => (bool) $alert['mail'],
            ];
        }

        $user->forceFill(['notification_preferences' => $prefs])->save();

        return back()->with('success', 'Notification preferences saved.');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return redirect()->route('admin.profile.edit')
            ->with('success', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        $user->password = $data['password'];
        $user->save();

        return redirect()->route('admin.profile.edit')
            ->with('success', 'Password updated.');
    }
}
