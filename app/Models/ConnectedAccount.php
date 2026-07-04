<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectedAccount extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_user_id', 'provider_username',
        'provider_email', 'avatar_url', 'access_token', 'refresh_token',
        'token_expires_at', 'scopes', 'raw_profile',
    ];

    /** Tokens are never exposed in serialization. */
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'raw_profile' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
