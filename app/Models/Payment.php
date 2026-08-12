<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A payment against any extension's purchasable model (payable). Core owns
 * the gateway integration and idempotency; extensions only ever create one
 * via PaymentService and react to it via ExtensionRegistry::payments().
 *
 * @property int $id
 * @property string $payable_type
 * @property int $payable_id
 * @property int|null $subscription_id
 * @property int|null $user_id
 * @property string $gateway
 * @property string|null $external_id
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $payable
 * @property-read User|null $user
 * @property-read Subscription|null $subscription
 * @property-read Invoice|null $invoice
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin \Eloquent
 */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'payable_type', 'payable_id', 'subscription_id', 'user_id', 'gateway',
        'external_id', 'amount', 'currency', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Human-readable label for invoices/transaction lists. Duck-typed, not a
     * formal contract — a payable optionally defines paymentDescription(),
     * same philosophy as ContentReport's bare morphTo() with no interface
     * obligation on the reportable model.
     */
    public function description(): string
    {
        $payable = $this->payable;

        if ($payable !== null && method_exists($payable, 'paymentDescription')) {
            return $payable->paymentDescription();
        }

        return class_basename($this->payable_type).' #'.$this->payable_id;
    }
}
