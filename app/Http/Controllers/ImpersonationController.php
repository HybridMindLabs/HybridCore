<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ActivityLogService $activity) {}

    /** Sign the admin in as the given user, remembering who they really are. */
    public function start(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot impersonate yourself.');
        abort_if($user->is_admin, 403, 'Admins cannot be impersonated.');
        abort_if($user->isBanned(), 403, 'Banned users cannot be impersonated.');

        $this->activity->log('user.impersonate_start', "Started impersonating {$user->email}", $user);

        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->put('impersonated_id', $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('success', "You are now browsing as {$user->name}.");
    }

    /** Return to the original admin account. */
    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $impersonatedId = $request->session()->pull('impersonated_id');

        abort_unless($impersonatorId, 403);

        $admin = User::findOrFail($impersonatorId);

        Auth::login($admin);
        $request->session()->regenerate();

        $this->activity->log('user.impersonate_stop', 'Stopped impersonating user #'.$impersonatedId);

        return $impersonatedId
            ? redirect()->route('admin.users.show', $impersonatedId)
            : redirect()->route('admin.users.index');
    }
}
