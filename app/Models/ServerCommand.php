<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property string $command
 * @property string $source
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $delivered_at
 * @property Carbon|null $acked_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereAckedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereCommand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereDeliveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerCommand whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ServerCommand extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_ACKED = 'acked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'server_id', 'command', 'source', 'status',
        'attempts', 'delivered_at', 'acked_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'acked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
