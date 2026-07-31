<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $blocker_id
 * @property int $blocked_id
 * @property Carbon $created_at
 * @property-read User $blocked
 * @property-read User $blocker
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock whereBlockedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock whereBlockerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBlock whereId($value)
 *
 * @mixin \Eloquent
 */
class UserBlock extends Model
{
    public $timestamps = false;

    protected $fillable = ['blocker_id', 'blocked_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
