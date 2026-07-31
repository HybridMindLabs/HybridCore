<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $provider_username
 * @property string|null $provider_email
 * @property string|null $avatar_url
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property array<array-key, mixed>|null $scopes
 * @property array<array-key, mixed>|null $raw_profile
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereProviderEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereProviderUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereProviderUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereRawProfile($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereScopes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ConnectedAccount whereUserId($value)
 *
 * @mixin \Eloquent
 */
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
