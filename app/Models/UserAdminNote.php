<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $author_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAdminNote whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserAdminNote extends Model
{
    protected $fillable = ['user_id', 'author_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
