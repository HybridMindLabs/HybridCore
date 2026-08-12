<?php

namespace App\Services\Payments;

use App\Jobs\ProcessPaymentEvent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The only thing an extension talks to. It never imports a payment SDK,
 * builds a webhook route, or verifies a signature — all of that is core's
 * job, hidden behind PaymentManager.
 *
 *   $url = $payments->checkout($package, $user, 999, 'usd', $successUrl, $cancelUrl);
 *   // ...and in the extension's service provider boot():
 *   $registry->payments()->on('paid', fn (Payment $payment) => deliverReward($payment));
 */
class PaymentService
{
    public function __construct(private readonly PaymentManager $manager) {}

    /** Creates a pending Payment for $payable and returns the checkout redirect URL. */
    public function checkout(
        Model $payable,
        User $buyer,
        int $amountCents,
        string $currency,
        string $successUrl,
        string $cancelUrl,
        ?string $gateway = null,
    ): string {
        $gateway ??= config('payments.default');

        $payment = Payment::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'user_id' => $buyer->id,
            'gateway' => $gateway,
            'amount' => $amountCents,
            'currency' => $currency,
            'status' => Payment::STATUS_PENDING,
        ]);

        return $this->manager->driver($gateway)->createCheckout($payment, $successUrl, $cancelUrl);
    }

    /**
     * Refunds a paid payment through its gateway and marks it refunded.
     * A later refund webhook for the same payment is then a no-op — the
     * status is already refunded, so it won't dispatch a second time.
     */
    public function refund(Payment $payment): void
    {
        $this->manager->driver($payment->gateway)->refund($payment);

        if ($payment->status !== Payment::STATUS_REFUNDED) {
            $payment->update(['status' => Payment::STATUS_REFUNDED]);
            ProcessPaymentEvent::dispatch($payment->id);
        }
    }
}
