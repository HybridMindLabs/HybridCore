<?php

namespace App\Services\Extensions\Registries;

/**
 * Lets extensions register their notification types so the frontend can render
 * them with a consistent icon and accent colour. The notification's own stored
 * `data` still supplies the message, action_url and action_label — this registry
 * only styles the row by its `type`.
 *
 *   $registry->notificationTypes()->register(
 *       type: 'vote.confirmed',
 *       icon: 'ThumbsUp',
 *       accent: 'blue',
 *   );
 */
class NotificationTypeRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $type  The notification's data.type value
     * @param  string  $icon  Lucide icon name
     * @param  string  $accent  Accent key: blue|emerald|amber|red|violet|zinc
     */
    public function register(string $type, string $icon = 'Bell', string $accent = 'blue'): void
    {
        $this->items[$type] = [
            'type' => $type,
            'icon' => $icon,
            'accent' => $accent,
        ];
    }

    /**
     * Registered types keyed by type, for a quick frontend lookup.
     *
     * @return array<string, array{icon: string, accent: string}>
     */
    public function compose(): array
    {
        $result = [];
        foreach ($this->items as $type => $item) {
            $result[$type] = ['icon' => $item['icon'], 'accent' => $item['accent']];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return array_values($this->items);
    }
}
