<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An admin-configured HTTP endpoint that receives core Hooks:: events as
 * signed POST requests. `secret` signs the payload (HMAC-SHA256); it is
 * never sent over the wire itself, only compared against on the receiver.
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $last_triggered_at
 * @property string|null $last_status
 * @property int|null $last_response_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, WebhookDelivery> $deliveries
 * @property-read int|null $deliveries_count
 */
class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'url', 'secret', 'events', 'is_active', 'created_by',
        'last_triggered_at', 'last_status', 'last_response_code',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            // Compared against on the receiving end, not transmitted as a
            // bearer credential — unlike an API token, an admin legitimately
            // needs to read this back later to configure the other side.
            'secret' => 'encrypted',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
