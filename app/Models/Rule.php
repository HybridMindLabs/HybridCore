<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string|null $content
 * @property bool $is_system
 * @property bool $published
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereIsSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule wherePublished($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Rule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
