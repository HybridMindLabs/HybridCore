<?php

namespace App\Services\Auth;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
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
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Login-count / time-based achievements ("regular", "veteran", …).
        $this->achievements->check($user);
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
