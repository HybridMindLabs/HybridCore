<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
