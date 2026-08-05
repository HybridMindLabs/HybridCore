<?php

namespace App\Services\Extensions\Registries;

/**
 * Lets an extension expose its own events as webhook-subscribable, without
 * core knowing the extension exists. A registered key becomes selectable in
 * Admin > Webhooks and is auto-bridged to WebhookDispatcher: firing it via
 * $registry->hooks()->fire($key, ...$args) queues a delivery to every active
 * endpoint subscribed to it. Mirrors AbilityRegistry's shape exactly.
 */
class WebhookEventRegistry
{
    /** @var array<string, array{label: string, group: string}> */
    private array $events = [];

    public function register(string $key, string $label, string $group = 'general'): void
    {
        $this->events[$key] = ['label' => $label, 'group' => $group];
    }

    /** @return array<string, array{label: string, group: string}> */
    public function all(): array
    {
        return $this->events;
    }
}
