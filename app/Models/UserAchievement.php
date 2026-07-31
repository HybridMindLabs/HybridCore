<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property Carbon $awarded_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement whereAwardedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserAchievement whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserAchievement extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'slug'];

    protected function casts(): array
    {
        return ['awarded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
