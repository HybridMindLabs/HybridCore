<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
