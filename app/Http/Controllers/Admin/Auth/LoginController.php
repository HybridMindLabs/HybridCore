<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if ($user->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => __('auth.suspended'),
            ]);
        }

        if (! $user->is_admin) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => __('auth.no_admin_access'),
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_admin_login', true);
            Auth::logout();

            return redirect()->route('auth.2fa.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
