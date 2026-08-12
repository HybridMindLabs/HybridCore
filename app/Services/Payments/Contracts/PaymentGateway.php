<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Implemented once per payment provider (Stripe today; PayPal or anything
 * bigger later is a new class implementing this, nothing else changes).
 */
interface PaymentGateway
{
    /**
     * Starts a hosted checkout for the payment and returns the URL to
     * redirect the buyer to. HybridCore never collects card data directly.
     */
    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): string;

    /**
     * Verifies the inbound webhook's signature and normalizes it.
     * Returns null if the signature is invalid or the event isn't recognized.
     */
    public function parseWebhook(Request $request): ?PaymentWebhookEvent;

    /** Issues a refund for a previously-paid payment. */
    public function refund(Payment $payment): void;
}
