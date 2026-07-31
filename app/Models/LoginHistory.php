<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $country
 * @property string|null $city
 * @property Carbon $created_at
 * @property-read User $user
 *
 * @method static \Database\Factories\LoginHistoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LoginHistory whereUserId($value)
 *
 * @mixin \Eloquent
 */
class LoginHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'ip_address', 'user_agent', 'country', 'city'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
