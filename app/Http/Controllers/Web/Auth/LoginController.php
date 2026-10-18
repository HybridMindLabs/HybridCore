<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginSecurityService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private readonly LoginSecurityService $security) {}

    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.index');
        }

        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $inputKey = $request->has('login') ? 'login' : 'email';

        $credentials = $request->validate([
            $inputKey => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim((string) $credentials[$inputKey]);
        $existing = User::findForAuth($login);

        if ($existing && $existing->isLockedOut()) {
            throw ValidationException::withMessages([
                $inputKey => __('auth.locked', ['minutes' => $this->security->lockoutRemainingMinutes($existing)]),
            ]);
        }

        $attemptCredentials = [
            'email' => $existing ? $existing->email : $login,
            'password' => $credentials['password'],
        ];

        if (! Auth::attempt($attemptCredentials, $request->boolean('remember'))) {
            if ($existing) {
                $this->security->recordFailedAttempt($existing);
            }

            throw ValidationException::withMessages([$inputKey => __('auth.failed')]);
        }

        $user = Auth::user();

        if ($user->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([$inputKey => __('auth.suspended')]);
        }

        $request->session()->regenerate();
        $this->security->recordLogin($user, $request);
        app(HookRegistry::class)->fire(Hooks::USER_LOGIN, $user);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa_user_id', $user->id);
            Auth::logout();

            return redirect()->route('auth.2fa.challenge');
        }

        return redirect()->intended(route('account.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
