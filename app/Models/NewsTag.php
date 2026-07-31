<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, NewsArticle> $articles
 * @property-read int|null $articles_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsTag whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class NewsTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(NewsArticle::class, 'news_article_tag', 'tag_id', 'article_id');
    }
}
