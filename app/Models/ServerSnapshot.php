<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property bool $is_online
 * @property string|null $failure_reason
 * @property string|null $name
 * @property string|null $map
 * @property int $players_online
 * @property int $players_max
 * @property int|null $ping
 * @property bool $is_password_protected
 * @property bool $vac_secured
 * @property string|null $game_version
 * @property Carbon $recorded_at
 * @property-read Collection<int, ServerPlayer> $players
 * @property-read int|null $players_count
 * @property-read Server $server
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereGameVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereIsOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereIsPasswordProtected($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereMap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot wherePing($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot wherePlayersMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot wherePlayersOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerSnapshot whereVacSecured($value)
 *
 * @mixin \Eloquent
 */
class ServerSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'server_id', 'is_online', 'failure_reason', 'name', 'map',
        'players_online', 'players_max', 'ping',
        'is_password_protected', 'vac_secured', 'game_version', 'recorded_at',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'players_online' => 'integer',
        'players_max' => 'integer',
        'ping' => 'integer',
        'is_password_protected' => 'boolean',
        'vac_secured' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(ServerPlayer::class, 'snapshot_id');
    }
}
