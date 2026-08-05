<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Mandatory-2FA policy for the admin panel (Admin > Settings > Security).
 *
 * Modeled on how GitHub/Okta roll out organization-wide MFA requirements:
 * a policy toggle plus a personal grace window that starts the first time
 * it applies to a given user, not one global cutover that locks everyone
 * out simultaneously the moment the setting is flipped on.
 */
class TwoFactorPolicy
{
    public function __construct(private readonly SettingsService $settings) {}

    public function isRequired(): bool
    {
        return $this->settings->get('security.require_2fa_for_admins', '1') === '1';
    }

    public function graceDays(): int
    {
        return (int) ($this->settings->get('security.require_2fa_grace_days', '3') ?: 3);
    }

    /** Starts $user's personal grace clock the first time the policy applies to them. Idempotent. */
    public function ensureClockStarted(User $user): void
    {
        if ($user->two_factor_required_since === null) {
            $user->forceFill(['two_factor_required_since' => now()])->save();
        }
    }

    /** Clears the clock — called once 2FA is confirmed, so disabling it later starts a fresh window. */
    public function resetClock(User $user): void
    {
        $user->forceFill(['two_factor_required_since' => null])->save();
    }

    private function deadline(User $user): ?Carbon
    {
        return $user->two_factor_required_since?->copy()->addDays($this->graceDays());
    }

    /** True once $user's grace window has elapsed without 2FA — the admin panel should refuse them. */
    public function isBlocked(User $user): bool
    {
        if (! $this->isRequired() || $user->hasTwoFactorEnabled()) {
            return false;
        }

        $deadline = $this->deadline($user);

        return $deadline !== null && now()->greaterThanOrEqualTo($deadline);
    }

    /** Days left before enforcement blocks $user, or null if the policy doesn't apply to them right now. */
    public function daysRemaining(User $user): ?int
    {
        if (! $this->isRequired() || $user->hasTwoFactorEnabled()) {
            return null;
        }

        $deadline = $this->deadline($user);

        if ($deadline === null) {
            return null;
        }

        if (now()->greaterThanOrEqualTo($deadline)) {
            return 0;
        }

        return (int) ceil(now()->diffInMinutes($deadline, absolute: true) / 1440);
    }
}
