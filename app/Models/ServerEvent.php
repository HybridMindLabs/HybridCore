<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $server_id
 * @property string $event_id
 * @property string $type
 * @property array<array-key, mixed>|null $data
 * @property Carbon|null $occurred_at
 * @property Carbon|null $created_at
 * @property-read Server $server
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServerEvent whereType($value)
 *
 * @mixin \Eloquent
 */
class ServerEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'server_id', 'event_id', 'type', 'data', 'occurred_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
