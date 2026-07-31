<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read Server $server
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerConnectionClick whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ServerConnectionClick extends Model
{
    public $timestamps = false;

    protected $fillable = ['server_id', 'user_id', 'ip_address', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
