<?php

namespace App\Services\Auth;

use App\Mail\AccountLockedMail;
use App\Mail\NewLoginMail;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\SettingsService;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/**
 * Shared login/registration security helpers. Never logs or returns
 * passwords or tokens.
 */
class LoginSecurityService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AchievementService $achievements,
    ) {}

    /** Record successful login metadata (no sensitive values). */
    public function recordLogin(User $user, Request $request): void
    {
        $ip = $request->ip();

        // A brand-new account's first-ever login is expected from an unseen
        // IP — only a login after that baseline is "new" in a way worth
        // alerting on.
        $isNewDevice = $ip !== null
            && $user->loginHistories()->exists()
            && ! $user->loginHistories()->where('ip_address', $ip)->exists();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
        ]);

        if ($isNewDevice) {
            $this->notifyNewLogin($user, $ip, $request->userAgent() ?? '');
        }

        // Login-count / time-based achievements ("regular", "veteran", …).
        $this->achievements->check($user);
    }

    private function notifyNewLogin(User $user, string $ip, string $userAgent): void
    {
        try {
            Mail::to($user->email)->queue(new NewLoginMail($user, $ip, UserAgent::summarize($userAgent)));
        } catch (\Exception) {
        }
    }

    /**
     * Record one wrong-password attempt against $user, independent of and in
     * addition to the per-minute/per-IP rate limiters — those only slow a
     * single attacker down, this locks the account itself after enough
     * consecutive misses regardless of where they came from.
     */
    public function recordFailedAttempt(User $user): void
    {
        $max = max(1, (int) config('hybridcore.max_failed_login_attempts', 5));
        $attempts = $user->failed_login_attempts + 1;
        $locked = $attempts >= $max;

        $user->forceFill([
            'failed_login_attempts' => $locked ? 0 : $attempts,
            'locked_until' => $locked
                ? now()->addMinutes(max(1, (int) config('hybridcore.lockout_minutes', 15)))
                : $user->locked_until,
        ])->save();

        if ($locked) {
            $this->notifyLocked($user);
        }
    }

    /** Minutes left before $user's lock lifts, or 0 if they aren't locked. */
    public function lockoutRemainingMinutes(User $user): int
    {
        if (! $user->isLockedOut()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($user->locked_until, absolute: true) / 60);
    }

    private function notifyLocked(User $user): void
    {
        try {
            Mail::to($user->email)->queue(new AccountLockedMail($user, (int) config('hybridcore.lockout_minutes', 15)));
        } catch (\Exception) {
        }
    }

    /** Password rule built from the configurable policy. */
    public function passwordRules(): Password
    {
        $min = max(8, (int) ($this->settings->get('password_min_length', '8') ?: 8));

        $rule = Password::min($min);

        if ($this->settings->get('password_require_mixed', '0') === '1') {
            $rule = $rule->mixedCase();
        }

        if ($this->settings->get('password_require_numbers', '1') === '1') {
            $rule = $rule->numbers();
        }

        return $rule;
    }

    public function registrationEnabled(): bool
    {
        return $this->settings->get('registration_enabled', '1') === '1';
    }

    public function emailVerificationRequired(): bool
    {
        return $this->settings->get('email_verification_required', '0') === '1';
    }

    public function defaultUserRoleSlug(): string
    {
        return (string) ($this->settings->get('default_user_role', 'member') ?: 'member');
    }
}
