<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One delivery attempt of one webhook event to one endpoint — the log an
 * admin reads to tell "is this integration actually working" apart from
 * WebhookEndpoint's last_status, which only remembers the most recent try.
 *
 * @property int $id
 * @property int $webhook_endpoint_id
 * @property string $event
 * @property bool $success
 * @property int|null $response_code
 * @property string|null $error
 * @property Carbon|null $created_at
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['webhook_endpoint_id', 'event', 'success', 'response_code', 'error'];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
