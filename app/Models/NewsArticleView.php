<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $article_id
 * @property int|null $user_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $viewed_at
 * @property-read NewsArticle|null $article
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView whereArticleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NewsArticleView whereViewedAt($value)
 *
 * @mixin \Eloquent
 */
class NewsArticleView extends Model
{
    public $timestamps = false;

    protected $fillable = ['article_id', 'user_id', 'ip', 'user_agent', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'article_id');
    }
}
