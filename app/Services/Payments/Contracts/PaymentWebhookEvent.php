<?php

namespace App\Services\Payments\Contracts;

/**
 * Normalized result of a gateway webhook, produced by
 * PaymentGateway::parseWebhook() once the signature has been verified.
 */
final readonly class PaymentWebhookEvent
{
    /**
     * @param  'payment'|'subscription_created'|'subscription_renewed'|'subscription_past_due'|'subscription_updated'|'subscription_canceled'  $type
     * @param  string  $externalId  Meaning depends on $type: a Payment's or a Subscription's external_id
     * @param  string|null  $referenceId  client_reference_id — only set on subscription_created, matches
     *                                    the Subscription row before its external_id exists
     * @param  'paid'|'failed'|'refunded'|null  $status  Only meaningful for 'payment'/'subscription_renewed'
     * @param  int|null  $amount  Cents — only for 'payment'/'subscription_renewed'
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $type,
        public string $externalId,
        public ?string $referenceId = null,
        public ?string $status = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $currentPeriodEnd = null,
        public ?bool $cancelAtPeriodEnd = null,
        public array $rawPayload = [],
    ) {}
}
