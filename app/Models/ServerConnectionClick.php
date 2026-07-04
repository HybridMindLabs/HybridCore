<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
