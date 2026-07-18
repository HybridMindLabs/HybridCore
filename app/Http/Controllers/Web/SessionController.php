<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    /** Return active sessions for the authenticated user. */
    public function index(Request $request): array
    {
        $currentId = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => $session->last_activity,
                'is_current' => $session->id === $currentId,
                'device' => $this->parseDevice($session->user_agent ?? ''),
            ])
            ->all();
    }

    /** Revoke a specific session (cannot revoke the current one). */
    public function destroy(Request $request, string $sessionId): JsonResponse|RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return $this->fail($request, __('account.sessions_cannot_revoke_current'));
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        return $this->ok($request, __('account.sessions_revoked'));
    }

    /** Revoke all sessions except the current one. */
    public function destroyOthers(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        $user = $request->user();

        // Deleting the rows alone does not sign anyone out for good: a device
        // that ticked "remember me" still holds a cookie that logs it straight
        // back in on its next request, in a brand new session.
        //
        // Laravel's own logoutOtherDevices() is no help here — it rehashes the
        // password and leans on the AuthenticateSession middleware, which this
        // app does not run. There is a single remember_token per user, so the
        // only way to kill those cookies is to cycle it, which invalidates this
        // device's cookie too; it gets a fresh one below.
        $hadRecaller = $request->cookies->has(Auth::guard()->getRecallerName());

        $user->setRememberToken(Str::random(60));
        $user->save();

        if ($hadRecaller) {
            // Re-issues the recaller against the new token. This also rotates
            // the session id, so the row to keep is read back afterwards.
            Auth::login($user, true);
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return $this->ok($request, __('account.sessions_revoked_all'));
    }

    /**
     * The account panel drives these over fetch() and reads res.json(). A
     * redirect made that parse throw, so a revoke that had actually gone
     * through still surfaced to the user as "Network error".
     */
    private function ok(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    private function fail(Request $request, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withErrors(['session' => $message]);
    }

    private function parseDevice(string $ua): array
    {
        $browser = match (true) {
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome')
                && ! str_contains($ua, 'Edg')
                && ! str_contains($ua, 'OPR') => 'Chrome',
            str_contains($ua, 'Safari')
                && ! str_contains($ua, 'Chrome') => 'Safari',
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'OPR') => 'Opera',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone')
                || str_contains($ua, 'iPad') => 'iOS',
            default => 'Unknown OS',
        };

        $mobile = str_contains($ua, 'Mobile') || str_contains($ua, 'Android') || str_contains($ua, 'iPhone');

        return ['browser' => $browser, 'os' => $os, 'mobile' => $mobile];
    }
}
