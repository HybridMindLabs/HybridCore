<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Rule extends Model
{
    public const LIST_CACHE_KEY = 'rules.published_list';

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::LIST_CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'slug',
        'title',
        'excerpt',
        'content',
        'is_system',
        'published',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'published' => 'boolean',
    ];

    public static function generateSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function scopePublished($query): void
    {
        $query->where('published', true);
    }
}
