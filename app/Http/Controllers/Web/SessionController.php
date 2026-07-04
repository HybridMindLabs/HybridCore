<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->withErrors(['session' => 'You cannot revoke your current session.']);
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'Session has been revoked.');
    }

    /** Revoke all sessions except the current one. */
    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'All other sessions have been revoked.');
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
