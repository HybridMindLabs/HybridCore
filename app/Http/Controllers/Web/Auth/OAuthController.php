<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\ConnectedAccountService;
use App\Services\Auth\OAuthProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Generic OAuth entry points. The core ships no provider implementations —
 * extensions register providers (and may override redirect/callback routes).
 * Until then these endpoints fail cleanly without leaking anything.
 */
class OAuthController extends Controller
{
    public function __construct(
        private readonly OAuthProviderRegistry $registry,
        private readonly ConnectedAccountService $accounts,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $def = $this->resolveEnabled($provider);

        // Extensions can point the redirect at their own implementation route.
        if (! empty($def['redirect_route']) && app('router')->has($def['redirect_route'])) {
            return redirect()->route($def['redirect_route']);
        }

        abort(503, __('auth.oauth_unavailable'));
    }

    public function callback(string $provider): RedirectResponse
    {
        $def = $this->resolveEnabled($provider);

        if (! empty($def['callback_route']) && app('router')->has($def['callback_route'])) {
            return redirect()->route($def['callback_route']);
        }

        abort(503, __('auth.oauth_unavailable'));
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->registry->has($provider), 404, __('auth.oauth_unknown'));

        $user = $request->user();

        // An account created through OAuth has no password its owner knows, so
        // unlinking the last provider would leave nothing to sign in with. The
        // page hides the button in that case; this is the check that counts.
        $isLastLogin = ! $user->hasUsablePassword()
            && $user->connectedAccounts()->where('provider', '!=', $provider)->doesntExist();

        if ($isLastLogin) {
            return back()->withErrors(['provider' => __('account.oauth_last_login')]);
        }

        $this->accounts->disconnect($user, $provider);

        return back()->with('success', __('account.account_disconnected'));
    }

    /** @return array<string, mixed> */
    private function resolveEnabled(string $provider): array
    {
        abort_unless($this->registry->has($provider), 404, __('auth.oauth_unknown'));
        abort_unless($this->registry->isEnabled($provider), 403, __('auth.oauth_disabled'));

        return $this->registry->get($provider);
    }
}
