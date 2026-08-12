<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A recurring subscription against any extension's purchasable model
 * (payable). Each successful renewal creates a normal Payment row linked
 * back here — extensions never need a separate "renewed" reward path,
 * ExtensionRegistry::payments()->on('paid', ...) already fires again on
 * every renewal.
 *
 * @property int $id
 * @property string $payable_type
 * @property int $payable_id
 * @property int $user_id
 * @property string $gateway
 * @property string|null $external_id
 * @property int $amount
 * @property string $currency
 * @property string $interval
 * @property string $status
 * @property Carbon|null $current_period_end
 * @property bool $cancel_at_period_end
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $payable
 * @property-read User $user
 * @property-read Collection<int, Payment> $payments
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin \Eloquent
 */
class Subscription extends Model
{
    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'payable_type', 'payable_id', 'user_id', 'gateway', 'external_id',
        'amount', 'currency', 'interval', 'status',
        'current_period_end', 'cancel_at_period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Same duck-type as Payment::description() — see that method's docblock. */
    public function description(): string
    {
        $payable = $this->payable;

        if ($payable !== null && method_exists($payable, 'paymentDescription')) {
            return $payable->paymentDescription();
        }

        return class_basename($this->payable_type).' #'.$this->payable_id;
    }
}
