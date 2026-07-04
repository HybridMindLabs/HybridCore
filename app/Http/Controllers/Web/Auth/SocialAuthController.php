<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ConnectedAccountService;
use App\Services\Auth\OAuthProviderRegistry;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Core OAuth login: Discord and Steam. Credentials are admin-configurable
 * (SettingsService), never read from .env — injected into config() per
 * request so Socialite's drivers pick them up transparently.
 *
 * Steam (OpenID, not real OAuth2) never returns an email. When that happens
 * for a brand-new account, we stash the profile in the session and send the
 * user to /auth/complete-profile to supply one before the account is created.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly OAuthProviderRegistry $registry,
        private readonly ConnectedAccountService $accounts,
        private readonly SettingsService $settings,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->configureProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->configureProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('error', __('auth.oauth_failed'));
        }

        $providerId = (string) $socialUser->getId();

        // Already linked to an account — log them in.
        $existing = $this->accounts->findUser($provider, $providerId);
        if ($existing) {
            $this->accounts->connect($existing, $provider, $this->profileData($socialUser));
            Auth::login($existing, true);

            return redirect()->intended(route('account.index'));
        }

        // Logged-in user connecting an additional provider.
        if ($request->user()) {
            try {
                $this->accounts->connect($request->user(), $provider, $this->profileData($socialUser));
            } catch (\DomainException) {
                return redirect()->route('account.index')->with('error', __('account.oauth_already_linked'));
            }

            return redirect()->route('account.index')->with('success', __('account.account_connected'));
        }

        $email = $socialUser->getEmail();

        // No email (Steam) — stash the profile, ask the user to supply one.
        if (! $email) {
            $request->session()->put('oauth.pending', [
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'name' => $socialUser->getName() ?: $socialUser->getNickname(),
                'avatar' => $socialUser->getAvatar(),
                'raw' => $socialUser->getRaw(),
            ]);

            return redirect()->route('oauth.complete-profile');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: $email,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ],
        );

        $this->accounts->connect($user, $provider, $this->profileData($socialUser));
        Auth::login($user, true);

        return redirect()->intended(route('account.index'));
    }

    public function showCompleteProfile(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('oauth.pending')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/CompleteProfile');
    }

    public function storeCompleteProfile(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('oauth.pending');
        abort_unless($pending, 419);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $socialBase = preg_replace('/[^a-z0-9_-]/i', '', strtolower(explode(' ', $pending['name'] ?? 'player')[0])) ?: 'player';
        $socialUsername = $socialBase;
        $si = 2;
        while (User::where('username', $socialUsername)->exists()) {
            $socialUsername = $socialBase.'_'.$si++;
        }

        $user = User::create([
            'name' => $pending['name'] ?? 'Player',
            'username' => $socialUsername,
            'email' => $data['email'],
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);

        $this->accounts->connect($user, $pending['provider'], [
            'provider_user_id' => $pending['provider_user_id'],
            'provider_username' => $pending['name'] ?? null,
            'avatar_url' => $pending['avatar'] ?? null,
            'raw_profile' => $pending['raw'] ?? null,
        ]);

        $request->session()->forget('oauth.pending');
        Auth::login($user, true);

        return redirect()->route('account.index');
    }

    private function configureProvider(string $provider): void
    {
        abort_unless($this->registry->isEnabled($provider), 403, __('auth.oauth_disabled'));

        $def = $this->registry->get($provider);

        config(["services.{$provider}" => [
            'client_id' => $this->settings->get($def['client_id_setting']),
            'client_secret' => $this->settings->get($def['client_secret_setting']),
            'redirect' => route("oauth.{$provider}.callback"),
        ]]);
    }

    /** @return array<string, mixed> */
    private function profileData(SocialiteUser $socialUser): array
    {
        return [
            'provider_user_id' => (string) $socialUser->getId(),
            'provider_username' => $socialUser->getNickname() ?: $socialUser->getName(),
            'provider_email' => $socialUser->getEmail(),
            'avatar_url' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'raw_profile' => $socialUser->getRaw(),
        ];
    }
}
