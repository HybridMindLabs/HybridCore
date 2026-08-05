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
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $display_name
 * @property Carbon|null $username_changed_at
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $password_set_at
 * @property string|null $two_factor_secret
 * @property array<array-key, mixed>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $two_factor_required_since
 * @property string|null $remember_token
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $banned_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WebauthnCredential> $webauthnCredentials
 * @property-read int|null $webauthn_credentials_count
 * @property string|null $timezone
 * @property string|null $locale
 * @property string|null $avatar
 * @property string|null $banner
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $website
 * @property string $profile_privacy
 * @property array<array-key, mixed>|null $notification_preferences
 * @property bool $is_admin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserAchievement> $achievements
 * @property-read int|null $achievements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserBlock> $blocks
 * @property-read int|null $blocks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ConnectedAccount> $connectedAccounts
 * @property-read int|null $connected_accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Server> $favouriteServers
 * @property-read int|null $favourite_servers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $followers
 * @property-read int|null $followers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $following
 * @property-read int|null $following_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LoginHistory> $loginHistories
 * @property-read int|null $login_histories_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Game> $preferredGames
 * @property-read int|null $preferred_games_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBannedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBanner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereNotificationPreferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOnboardingCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePasswordSetAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereProfilePrivacy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsernameChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereWebsite($value)
 *
 * @mixin \Eloquent
 */
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
        return ! $this->isBanned();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'is_admin' => 'boolean',
            'banned_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'username_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_required_since' => 'datetime',
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

    // ── Display ──────────────────────────────────────────────────────────────

    /** The visible name: display_name if set, otherwise username. */
    public function getDisplayNameAttribute(?string $value): string
    {
        return ($value ?: null) ?? $this->username ?? '?';
    }

    /** True with either a confirmed TOTP secret or at least one registered passkey. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null || $this->webauthnCredentials()->exists();
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebauthnCredential::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
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

    /** @return HasMany<UserBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    /** @return BelongsToMany<Server, $this> */
    public function favouriteServers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_favourites');
    }

    /** Games the user picked in Account → Preferences. */
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

    /** @return HasMany<LoginHistory, $this> */
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
