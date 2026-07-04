<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'server_id', 'is_online', 'name', 'map',
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
