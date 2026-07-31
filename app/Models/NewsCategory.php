<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $color
 * @property string $icon
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, NewsArticle> $articles
 * @property-read int|null $articles_count
 * @property-read Collection<int, NewsArticle> $publishedArticles
 * @property-read int|null $published_articles_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereMetaDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereMetaTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsCategory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class NewsCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'color', 'icon',
        'meta_title', 'meta_description', 'sort_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function articles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'category_id')
            ->where('status', 'published')
            ->where('published_at', '<=', now());
    }
}
