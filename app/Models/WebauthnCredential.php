<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A passkey/security key registered as a WebAuthn second factor. Multiple
 * per user are normal (a laptop's Touch ID, a phone, a hardware key).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $credential_id
 * @property string $public_key
 * @property int $sign_counter
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebauthnCredential extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'credential_id', 'public_key', 'sign_counter', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
