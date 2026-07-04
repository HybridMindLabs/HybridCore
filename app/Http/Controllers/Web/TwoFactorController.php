<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AchievementService $achievements,
    ) {}

    /** Generate a new secret + QR and return to the frontend (not saved yet). */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey();

        // Store temporarily in session until confirmed
        $request->session()->put('2fa_setup_secret', $secret);

        $qrUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return response()->json(['secret' => $secret, 'qr_url' => $qrUrl]);
    }

    /** Confirm the code and persist 2FA on the account. */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $secret = $request->session()->get('2fa_setup_secret');

        if (! $secret || ! $this->google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'The code is incorrect. Please try again.'], 422);
        }

        $user = $request->user();

        $recoveryCodes = Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ]);

        $request->session()->forget('2fa_setup_secret');
        $this->achievements->check($user);

        return response()->json(['message' => 'Two-factor authentication has been enabled.']);
    }

    /** Disable 2FA after password confirmation. */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => 'Two-factor authentication has been disabled.']);
    }

    /** Regenerate recovery codes. */
    public function regenerateCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        $recoveryCodes = Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all();

        $request->user()->update(['two_factor_recovery_codes' => $recoveryCodes]);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    /** Show the 2FA challenge page after password login. */
    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /** Verify 2FA code during login (challenge step). */
    public function challenge(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string']]);

        $user = User::findOrFail($userId);

        $raw = $request->string('code')->replace(' ', '')->toString();
        $code = str_replace('-', '', $raw);

        // Try TOTP code
        if (strlen($code) === 6 && $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            $request->session()->forget('2fa_user_id');
            Auth::login($user, $request->session()->get('2fa_remember', false));
            $request->session()->put('2fa_verified', true);

            return redirect()->intended(route('account.index'));
        }

        // Try recovery code
        $codes = $user->two_factor_recovery_codes ?? [];
        if (in_array($raw, $codes, true)) {
            $user->update([
                'two_factor_recovery_codes' => array_values(array_filter($codes, fn ($c) => $c !== $raw)),
            ]);
            $request->session()->forget('2fa_user_id');
            Auth::login($user);
            $request->session()->put('2fa_verified', true);

            return redirect()->intended(route('account.index'));
        }

        return back()->withErrors(['code' => 'Invalid code. Try again or use a recovery code.']);
    }
}
