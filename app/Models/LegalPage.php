<?php

namespace App\Models;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $content
 * @property Carbon|null $content_updated_at
 * @property bool $is_system
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereContentUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereIsSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereSubtitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LegalPage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LegalPage extends Model
{
    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(HandleInertiaRequests::LEGAL_PAGES_CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'content',
        'content_updated_at',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'content_updated_at' => 'date:Y-m-d',
        'is_system' => 'boolean',
    ];

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
