<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerCommand extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_ACKED = 'acked';

    public const STATUS_EXPIRED = 'expired';

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
