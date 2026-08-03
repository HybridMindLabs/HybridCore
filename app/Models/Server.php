<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $game_id
 * @property string $ip
 * @property int $port
 * @property int|null $query_port
 * @property string|null $name
 * @property string|null $country_code
 * @property array<array-key, mixed>|null $tags
 * @property bool $is_active
 * @property int|null $added_by
 * @property Carbon|null $last_queried_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $bridge_token_hash
 * @property bool $bridge_enabled
 * @property Carbon|null $bridge_last_seen_at
 * @property-read User|null $addedBy
 * @property-read Collection<int, ServerCommand> $bridgeCommands
 * @property-read int|null $bridge_commands_count
 * @property-read Collection<int, ServerConnectionClick> $connectionClicks
 * @property-read int|null $connection_clicks_count
 * @property-read Collection<int, User> $favouritedBy
 * @property-read int|null $favourited_by_count
 * @property-read Game $game
 * @property-read string $address
 * @property-read float|null $average_rating
 * @property-read ServerSnapshot|null $cached_snapshot
 * @property-read bool $is_online
 * @property-read ServerSnapshot|null $latestSnapshot
 * @property-read Collection<int, ServerReview> $serverReviews
 * @property-read int|null $server_reviews_count
 * @property-read Collection<int, ServerSnapshot> $snapshots
 * @property-read int|null $snapshots_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server active()
 * @method static \Database\Factories\ServerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereAddedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereBridgeEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereBridgeLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereBridgeTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereLastQueriedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereQueryPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Server extends Model
{
    use HasFactory;
    use Searchable;

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'ip' => $this->ip,
            'tags' => implode(' ', $this->tags ?? []),
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) $this->is_active;
    }

    protected $fillable = [
        'game_id', 'ip', 'port', 'query_port', 'name', 'country_code',
        'tags', 'is_active', 'added_by', 'last_queried_at',
    ];

    // bridge_token_hash is intentionally NOT fillable — it is only ever set
    // via BridgeService::issueToken()/revokeToken() (forceFill).
    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
        'port' => 'integer',
        'query_port' => 'integer',
        'last_queried_at' => 'datetime',
        'bridge_enabled' => 'boolean',
        'bridge_last_seen_at' => 'datetime',
    ];

    /** Hide the token hash from any serialized output. */
    protected $hidden = ['bridge_token_hash'];

    public function bridgeCommands(): HasMany
    {
        return $this->hasMany(ServerCommand::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ServerSnapshot::class);
    }

    /** @return HasOne<ServerSnapshot, $this> */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(ServerSnapshot::class)->latestOfMany('recorded_at');
    }

    public function favouritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'server_favourites')->withPivot('created_at');
    }

    public function connectionClicks(): HasMany
    {
        return $this->hasMany(ServerConnectionClick::class);
    }

    /** @return HasMany<ServerReview, $this> */
    public function serverReviews(): HasMany
    {
        return $this->hasMany(ServerReview::class);
    }

    public function getAddressAttribute(): string
    {
        return "{$this->ip}:{$this->port}";
    }

    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->serverReviews()->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->latestSnapshot?->is_online ?? false;
    }

    public function getCachedSnapshotAttribute(): ?ServerSnapshot
    {
        return Cache::remember('server.snapshot.'.$this->id, 60, fn () => $this->latestSnapshot
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
