<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $icon
 * @property string $color
 * @property string $query_driver
 * @property int $default_port
 * @property int|null $default_query_port
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Server> $activeServers
 * @property-read int|null $active_servers_count
 * @property-read string|null $cover_url
 * @property-read int $online_servers_count
 * @property-read int $total_players_online
 * @property-read Collection<int, Server> $servers
 * @property-read int|null $servers_count
 * @property-read Collection<int, ServerSnapshot> $snapshots
 * @property-read int|null $snapshots_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game active()
 * @method static \Database\Factories\GameFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereDefaultPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereDefaultQueryPort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereQueryDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Game whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'icon', 'color', 'query_driver',
        'default_port', 'default_query_port', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_port' => 'integer',
        'default_query_port' => 'integer',
        'sort_order' => 'integer',
    ];

    public function getCoverUrlAttribute(): ?string
    {
        $dir = public_path("images/covers/{$this->slug}");

        if (! is_dir($dir)) {
            return null;
        }

        // Prefer the most efficient format available: WebP/AVIF before the
        // heavier raster formats, whatever was dropped into the folder.
        foreach (['webp', 'avif', 'png', 'jpg', 'jpeg', 'gif'] as $ext) {
            $match = glob("{$dir}/*.{$ext}");
            if (! empty($match)) {
                return asset('images/covers/'.$this->slug.'/'.basename($match[0]));
            }
        }

        return null;
    }

    /**
     * Backdrop for a server row in the public listings.
     *
     * Prefers a screenshot of the map actually being played; falls back to the
     * game's own row artwork in public/images/server_rows/{slug}.{ext} so rows
     * still get their texture before any map screenshots are uploaded.
     */
    public static function rowImageUrl(string $gameSlug, ?string $map): ?string
    {
        if ($mapImage = self::mapImageUrl($gameSlug, $map)) {
            return $mapImage;
        }

        $slug = preg_replace('/[^a-z0-9_\-]/i', '', $gameSlug);

        if ($slug === '') {
            return null;
        }

        foreach (['webp', 'avif', 'png', 'jpg', 'jpeg'] as $ext) {
            $file = public_path("images/server_rows/{$slug}.{$ext}");
            if (is_file($file)) {
                return asset("images/server_rows/{$slug}.{$ext}");
            }
        }

        return null;
    }

    /**
     * Thumbnail for a map, e.g. public/images/maps/cs2/de_dust2.jpg.
     * Extension-agnostic — whatever image is dropped in that folder is used.
     */
    public static function mapImageUrl(string $gameSlug, ?string $map): ?string
    {
        if (! $map) {
            return null;
        }

        $mapSlug = preg_replace('/[^a-z0-9_\-]/i', '', $map);
        $dir = public_path("images/maps/{$gameSlug}");

        if ($mapSlug === '' || ! is_dir($dir)) {
            return null;
        }

        $files = glob("{$dir}/{$mapSlug}.{jpg,jpeg,png,webp,avif}", GLOB_BRACE);

        if (empty($files)) {
            return null;
        }

        return asset("images/maps/{$gameSlug}/".basename($files[0]));
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function activeServers(): HasMany
    {
        return $this->hasMany(Server::class)->where('is_active', true);
    }

    public function snapshots(): HasManyThrough
    {
        return $this->hasManyThrough(ServerSnapshot::class, Server::class);
    }

    public function getTotalPlayersOnlineAttribute(): int
    {
        return $this->activeServers()
            ->with('latestSnapshot')
            ->get()
            ->sum(fn ($s) => $s->latestSnapshot?->players_online ?? 0);
    }

    public function getOnlineServersCountAttribute(): int
    {
        return $this->activeServers()
            ->whereHas('latestSnapshot', fn ($q) => $q->where('is_online', true))
            ->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
