<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $article_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read NewsArticle|null $article
 * @property-read User $user
 *
 * @method static Builder<static>|NewsComment newModelQuery()
 * @method static Builder<static>|NewsComment newQuery()
 * @method static Builder<static>|NewsComment onlyTrashed()
 * @method static Builder<static>|NewsComment query()
 * @method static Builder<static>|NewsComment whereArticleId($value)
 * @method static Builder<static>|NewsComment whereBody($value)
 * @method static Builder<static>|NewsComment whereCreatedAt($value)
 * @method static Builder<static>|NewsComment whereDeletedAt($value)
 * @method static Builder<static>|NewsComment whereId($value)
 * @method static Builder<static>|NewsComment whereUpdatedAt($value)
 * @method static Builder<static>|NewsComment whereUserId($value)
 * @method static Builder<static>|NewsComment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|NewsComment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class NewsComment extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Days a trashed comment stays restorable before being purged. */
    public const TRASH_RETENTION_DAYS = 30;

    protected $fillable = ['article_id', 'user_id', 'body'];

    public function prunable(): Builder
    {
        return static::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(self::TRASH_RETENTION_DAYS));
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
