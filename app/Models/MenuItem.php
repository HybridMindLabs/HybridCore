<?php

namespace App\Models;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $label
 * @property string $url
 * @property string $target
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MenuItem> $children
 * @property-read int|null $children_count
 * @property-read Menu $menu
 * @property-read MenuItem|null $parent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereMenuId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereSort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MenuItem whereUrl($value)
 *
 * @mixin \Eloquent
 */
class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'parent_id', 'label', 'url', 'target', 'sort'];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(HandleInertiaRequests::MENUS_CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort');
    }
}
