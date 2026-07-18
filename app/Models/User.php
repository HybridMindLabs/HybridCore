<?php

namespace App\Models;

use App\Services\SettingsService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;

#[Fillable([
    'name', 'username', 'display_name', 'email', 'password', 'password_set_at',
    'is_admin', 'banned_at', 'last_login_at', 'last_login_ip',
    'timezone', 'locale', 'avatar', 'banner', 'bio', 'location', 'website',
    'profile_privacy', 'notification_preferences', 'username_changed_at',
    'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
    'onboarding_completed_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /**
     * Email categories the user can switch off, stored as keys in the
     * notification_preferences JSON map. A key must only appear here once
     * something actually consults it before sending — see
     * NewMessageNotification::via() and EmailDigestCommand.
     */
    public const EMAIL_PREFERENCE_KEYS = ['email_messages', 'email_digest'];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use Searchable;

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return [
            'username' => $this->username,
            'display_name' => $this->display_name,
            'name' => $this->name,
        ];
    }

    /** Banned users stay out of the search index. */
    public function shouldBeSearchable(): bool
    {
        return ! $this->isBanned() && $this->username !== null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'is_admin' => 'boolean',
            'banned_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'username_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_secret' => 'encrypted',
            'notification_preferences' => 'array',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Whether the user can sign in with a password they know.
     *
     * OAuth signups are stored with a random placeholder hash, so the password
     * column being populated proves nothing on its own.
     */
    public function hasUsablePassword(): bool
    {
        return $this->password_set_at !== null;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    // ── Display ──────────────────────────────────────────────────────────────

    /** The visible name: display_name if set, otherwise username. */
    public function getDisplayNameAttribute(?string $value): string
    {
        return ($value ?: null) ?? $this->username ?? '?';
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')->withPivot('is_primary');
    }

    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(ConnectedAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'participant_1_id')
            ->orWhere('participant_2_id', $this->id);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function favouriteServers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_favourites');
    }

    /** Games the user marked as favourites during onboarding. */
    public function preferredGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'user_game_preferences')->withTimestamps();
    }

    /** Users this user follows. */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    /** Users following this user. */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    /**
     * Total unread DMs across all conversations, in a single query
     * (instead of one COUNT per conversation).
     */
    public function unreadMessagesCount(): int
    {
        return Message::whereNull('read_at')
            ->where('sender_id', '!=', $this->id)
            ->whereHas('conversation', fn ($q) => $q
                ->where('participant_1_id', $this->id)
                ->orWhere('participant_2_id', $this->id))
            ->count();
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->orderBy('created_at', 'desc');
    }

    // ── Permissions ──────────────────────────────────────────────────────────

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $owned = $this->permissionSlugs();

        if ($owned->contains('*')) {
            return true;
        }

        return $owned->contains(function (string $owned) use ($slug) {
            return $owned === $slug || Str::is($owned, $slug);
        });
    }

    /** @return Collection<int, string> */
    public function permissionSlugs(): Collection
    {
        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissionSlugsCached())
            ->unique()
            ->values();
    }

    public function primaryRole(): ?Role
    {
        $roles = $this->roles;

        return $roles->first(fn (Role $role) => (bool) $role->pivot->is_primary)
            ?? $roles->sortBy('sort')->first();
    }

    // ── Social helpers ───────────────────────────────────────────────────────

    public function hasBlocked(int $userId): bool
    {
        return $this->blocks()->where('blocked_id', $userId)->exists();
    }

    public function isBlockedBy(int $userId): bool
    {
        return UserBlock::where('blocker_id', $userId)->where('blocked_id', $this->id)->exists();
    }

    public function canChangUsername(): bool
    {
        if (! $this->username_changed_at) {
            return true;
        }

        $cooldownDays = (int) app(SettingsService::class)->get('username_change_cooldown_days', 30);

        return $this->username_changed_at->addDays($cooldownDays)->isPast();
    }
}
