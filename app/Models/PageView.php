<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $session_id
 * @property int|null $user_id
 * @property string $ip_hash
 * @property string $path
 * @property string|null $route_name
 * @property string $device_type
 * @property string|null $country_code
 * @property bool $is_bot
 * @property Carbon $created_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereIpHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereIsBot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereRouteName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PageView whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id', 'user_id', 'ip_hash', 'path',
        'route_name', 'device_type', 'country_code', 'is_bot',
    ];

    protected $casts = ['is_bot' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
