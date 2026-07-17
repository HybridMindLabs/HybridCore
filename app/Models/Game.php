<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
