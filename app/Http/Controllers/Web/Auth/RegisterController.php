<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Mail\WelcomeMail;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\LoginSecurityService;
use App\Services\Extensions\Registries\HookRegistry;
use App\Support\Hooks;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private readonly LoginSecurityService $security) {}

    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account.index');
        }

        if (! $this->security->registrationEnabled()) {
            return Inertia::render('Auth/Register', ['registrationEnabled' => false]);
        }

        return Inertia::render('Auth/Register', ['registrationEnabled' => true]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $base = preg_replace('/[^a-z0-9_-]/i', '', strtolower(explode(' ', $data['name'])[0])) ?: 'user';
        $username = $base;
        $i = 2;
        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$i++;
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
        ]);

        $role = Role::where('slug', $this->security->defaultUserRoleSlug())->first();
        if ($role) {
            $user->roles()->attach($role);
        }

        event(new Registered($user));

        try {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        } catch (\Exception) {
        }

        app(HookRegistry::class)->fire(Hooks::USER_REGISTERED, $user);

        Auth::login($user);
        $request->session()->regenerate();
        $this->security->recordLogin($user, $request);

        if ($this->security->emailVerificationRequired()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('onboarding.show');
    }
}
