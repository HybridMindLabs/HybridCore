<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\AchievementService;
use App\Services\TwoFactorPolicy;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AchievementService $achievements,
        private readonly TwoFactorPolicy $twoFactorPolicy,
    ) {}

    /** Generate a new secret + QR and return to the frontend (not saved yet). */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey();

        // Store temporarily in session until confirmed
        $request->session()->put('2fa_setup_secret', $secret);

        $otpauthUri = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->qrDataUri($otpauthUri),
            'otpauth_uri' => $otpauthUri,
        ]);
    }

    /**
     * getQRCodeUrl() returns an otpauth:// URI — the payload a scanner reads,
     * not something a browser can draw. Handing it straight to <img src> is why
     * the QR came up blank; it has to be rendered into an actual image first.
     *
     * White is painted in rather than left transparent so the code still scans
     * on a dark page and survives being screenshotted or printed.
     */
    private function qrDataUri(string $otpauthUri): string
    {
        $renderer = new ImageRenderer(
            // Margin is in modules; the QR spec wants a 4-module quiet zone or
            // scanners start missing the code against a busy background.
            new RendererStyle(240, 4, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0))),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($otpauthUri);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Confirm the code and persist 2FA on the account. */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $secret = $request->session()->get('2fa_setup_secret');

        if (! $secret || ! $this->google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => __('account.2fa_code_invalid')], 422);
        }

        $user = $request->user();

        $recoveryCodes = Collection::times(8, fn () => Str::random(10).'-'.Str::random(10))->all();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ]);

        $request->session()->forget('2fa_setup_secret');
        $this->twoFactorPolicy->resetClock($user);
        $this->achievements->check($user);

        return response()->json(['message' => __('account.2fa_was_enabled')]);
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

        return response()->json(['message' => __('account.2fa_was_disabled')]);
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
        $userId = $request->session()->get('2fa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'hasTotp' => User::whereKey($userId)->whereNotNull('two_factor_secret')->exists(),
            'hasWebauthn' => WebauthnCredential::where('user_id', $userId)->exists(),
        ]);
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
        $isAdminLogin = $request->session()->get('2fa_admin_login', false);
        $destination = $isAdminLogin ? route('admin.dashboard') : route('account.index');

        $raw = $request->string('code')->replace(' ', '')->toString();
        $code = str_replace('-', '', $raw);

        // Try TOTP code
        if ($user->two_factor_secret !== null && strlen($code) === 6 && $this->google2fa->verifyKey($user->two_factor_secret, $code)) {
            $request->session()->forget(['2fa_user_id', '2fa_admin_login']);
            Auth::login($user, $request->session()->get('2fa_remember', false));

            // The session id was handed out before the second factor was
            // proven, so it gets replaced on the way in rather than carried
            // over into the authenticated session.
            $request->session()->regenerate();
            $request->session()->put('2fa_verified', true);

            return redirect()->intended($destination);
        }

        // Try recovery code
        $codes = $user->two_factor_recovery_codes ?? [];
        if (in_array($raw, $codes, true)) {
            $user->update([
                'two_factor_recovery_codes' => array_values(array_filter($codes, fn ($c) => $c !== $raw)),
            ]);
            $request->session()->forget(['2fa_user_id', '2fa_admin_login']);
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('2fa_verified', true);

            return redirect()->intended($destination);
        }

        return back()->withErrors(['code' => __('account.2fa_challenge_invalid')]);
    }

    // ── WebAuthn / passkeys — a phishing-resistant alternative second factor
    // alongside TOTP, not a replacement for the password step. ──────────────

    /**
     * rpId must be the current host (no scheme, no port) and stays consistent
     * because the account owner registers and later signs in from the same
     * domain. base64url mode makes every ByteBuffer json_encode() straight to
     * what the browser's WebAuthn API expects, no manual (de)serialization.
     */
    private function webAuthn(Request $request): WebAuthn
    {
        return new WebAuthn(config('app.name'), $request->getHost(), null, true);
    }

    /** Begin registering a new passkey for the authenticated user. */
    public function webauthnRegisterOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $webAuthn = $this->webAuthn($request);

        $excludeIds = $user->webauthnCredentials()->pluck('credential_id')
            ->map(fn (string $id) => ByteBuffer::fromBase64Url($id))
            ->all();

        $args = $webAuthn->getCreateArgs(
            (string) $user->id,
            $user->username ?? $user->name,
            $user->display_name ?? $user->name,
            excludeCredentialIds: $excludeIds,
        );

        $request->session()->put('webauthn_challenge', $webAuthn->getChallenge()->jsonSerialize());

        return response()->json($args);
    }

    /** Verify the browser's attestation and store the new passkey. */
    public function webauthnRegister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'clientDataJSON' => ['required', 'string'],
            'attestationObject' => ['required', 'string'],
        ]);

        $challenge = $request->session()->get('webauthn_challenge');

        if (! $challenge) {
            return response()->json(['message' => __('account.2fa_network_error')], 422);
        }

        try {
            $result = $this->webAuthn($request)->processCreate(
                ByteBuffer::fromBase64Url($data['clientDataJSON'])->getBinaryString(),
                ByteBuffer::fromBase64Url($data['attestationObject'])->getBinaryString(),
                $challenge,
            );
        } catch (WebAuthnException) {
            return response()->json(['message' => __('account.2fa_code_invalid')], 422);
        }

        $user = $request->user();

        $user->webauthnCredentials()->create([
            'name' => $data['name'],
            'credential_id' => $result->credentialId->jsonSerialize(),
            'public_key' => $result->credentialPublicKey,
            'sign_counter' => $result->signatureCounter ?? 0,
        ]);

        $request->session()->forget('webauthn_challenge');
        $this->twoFactorPolicy->resetClock($user);
        $this->achievements->check($user);

        return response()->json(['message' => __('account.2fa_was_enabled')]);
    }

    /** Remove one of the authenticated user's own passkeys. */
    public function webauthnDestroy(Request $request, WebauthnCredential $credential): JsonResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 403);

        $credential->delete();

        return response()->json(['message' => __('account.2fa_was_disabled')]);
    }

    /** Begin the passkey challenge for the account mid-login (2fa_user_id already in session). */
    public function webauthnChallengeOptions(Request $request): JsonResponse
    {
        $userId = $request->session()->get('2fa_user_id');

        if (! $userId) {
            return response()->json(['message' => __('account.2fa_network_error')], 419);
        }

        $webAuthn = $this->webAuthn($request);

        $credentialIds = WebauthnCredential::where('user_id', $userId)->pluck('credential_id')
            ->map(fn (string $id) => ByteBuffer::fromBase64Url($id))
            ->all();

        $args = $webAuthn->getGetArgs($credentialIds);
        $request->session()->put('webauthn_challenge', $webAuthn->getChallenge()->jsonSerialize());

        return response()->json($args);
    }

    /** Verify the passkey assertion and complete the login. */
    public function webauthnChallengeVerify(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'id' => ['required', 'string'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $challenge = $request->session()->get('webauthn_challenge');
        $credential = WebauthnCredential::where('credential_id', $data['id'])->where('user_id', $userId)->first();

        if (! $challenge || ! $credential) {
            return back()->withErrors(['code' => __('account.2fa_challenge_invalid')]);
        }

        $webAuthn = $this->webAuthn($request);

        try {
            $ok = $webAuthn->processGet(
                ByteBuffer::fromBase64Url($data['clientDataJSON'])->getBinaryString(),
                ByteBuffer::fromBase64Url($data['authenticatorData'])->getBinaryString(),
                ByteBuffer::fromBase64Url($data['signature'])->getBinaryString(),
                $credential->public_key,
                $challenge,
                $credential->sign_counter,
            );
        } catch (WebAuthnException) {
            $ok = false;
        }

        if (! $ok) {
            return back()->withErrors(['code' => __('account.2fa_challenge_invalid')]);
        }

        $credential->update([
            'sign_counter' => $webAuthn->getSignatureCounter() ?? $credential->sign_counter,
            'last_used_at' => now(),
        ]);

        $user = User::findOrFail($userId);
        $isAdminLogin = $request->session()->get('2fa_admin_login', false);
        $destination = $isAdminLogin ? route('admin.dashboard') : route('account.index');

        $request->session()->forget(['2fa_user_id', '2fa_admin_login', 'webauthn_challenge']);
        Auth::login($user, $request->session()->get('2fa_remember', false));
        $request->session()->regenerate();
        $request->session()->put('2fa_verified', true);

        return redirect()->intended($destination);
    }
}
