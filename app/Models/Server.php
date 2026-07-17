<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

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
