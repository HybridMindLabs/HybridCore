<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $server_id
 * @property int $rating
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 * @property-read User $user
 *
 * @method static \Database\Factories\ServerReviewFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerReview whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ServerReview extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'server_id', 'rating', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
