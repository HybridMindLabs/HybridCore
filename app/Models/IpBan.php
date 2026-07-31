<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ip
 * @property string|null $reason
 * @property int|null $banned_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $bannedBy
 *
 * @method static Builder<static>|IpBan active()
 * @method static \Database\Factories\IpBanFactory factory($count = null, $state = [])
 * @method static Builder<static>|IpBan newModelQuery()
 * @method static Builder<static>|IpBan newQuery()
 * @method static Builder<static>|IpBan query()
 * @method static Builder<static>|IpBan whereBannedBy($value)
 * @method static Builder<static>|IpBan whereCreatedAt($value)
 * @method static Builder<static>|IpBan whereExpiresAt($value)
 * @method static Builder<static>|IpBan whereId($value)
 * @method static Builder<static>|IpBan whereIp($value)
 * @method static Builder<static>|IpBan whereReason($value)
 * @method static Builder<static>|IpBan whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class IpBan extends Model
{
    use HasFactory;

    protected $fillable = ['ip', 'reason', 'banned_by', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
