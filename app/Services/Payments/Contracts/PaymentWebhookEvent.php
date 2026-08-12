<?php

namespace App\Services\Payments\Contracts;

/**
 * Normalized result of a gateway webhook, produced by
 * PaymentGateway::parseWebhook() once the signature has been verified.
 */
final readonly class PaymentWebhookEvent
{
    /**
     * @param  'paid'|'failed'|'refunded'  $status
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $externalId,
        public string $status,
        public array $rawPayload,
    ) {}
}
