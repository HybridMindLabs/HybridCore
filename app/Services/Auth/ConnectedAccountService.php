<?php

namespace App\Services\Auth;

use App\Models\ConnectedAccount;
use App\Models\User;
use App\Services\AchievementService;

/**
 * Stores and removes connected (OAuth) accounts. Tokens are encrypted by
 * the model cast; this service never logs them.
 */
class ConnectedAccountService
{
    public function __construct(private readonly AchievementService $achievements) {}

    /**
     * Link a provider account to a user. Updates the existing link when the
     * user already has one for this provider.
     *
     * @param  array{provider_user_id: string, provider_username?: string|null, provider_email?: string|null, avatar_url?: string|null, access_token?: string|null, refresh_token?: string|null, token_expires_at?: \DateTimeInterface|null, scopes?: array<int, string>|null, raw_profile?: array<string, mixed>|null}  $data
     */
    public function connect(User $user, string $provider, array $data): ConnectedAccount
    {
        // A provider identity may only belong to one user.
        $existing = ConnectedAccount::where('provider', $provider)
            ->where('provider_user_id', $data['provider_user_id'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            throw new \DomainException('This account is already linked to another user.');
        }

        $account = ConnectedAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            [
                'provider_user_id' => $data['provider_user_id'],
                'provider_username' => $data['provider_username'] ?? null,
                'provider_email' => $data['provider_email'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => $data['token_expires_at'] ?? null,
                'scopes' => $data['scopes'] ?? null,
                'raw_profile' => $data['raw_profile'] ?? null,
            ],
        );

        $this->achievements->check($user);

        return $account;
    }

    public function disconnect(User $user, string $provider): bool
    {
        return (bool) ConnectedAccount::where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();
    }

    /** Find the user linked to a provider identity, if any. */
    public function findUser(string $provider, string $providerUserId): ?User
    {
        return ConnectedAccount::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first()
            ?->user;
    }
}
