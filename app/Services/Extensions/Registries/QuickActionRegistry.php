<?php

namespace App\Services\Extensions\Registries;

/**
 * Collects admin command-palette / quick-action entries from extensions.
 * Rendered in the admin command palette (Ctrl/Cmd+K).
 *
 *   $registry->quickActions()->register(
 *       label: 'vote::messages.settings',
 *       route: 'admin.vote.settings',
 *       icon: 'ThumbsUp',
 *       permission: 'vote.manage',
 *       keywords: ['vote', 'voting', 'rewards'],
 *   );
 */
class QuickActionRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $label  Display label (translation key or literal)
     * @param  string  $route  Named route (must exist to be shown)
     * @param  string  $icon  Lucide icon name
     * @param  string|null  $permission  Permission slug required; null = everyone
     * @param  array<int, string>  $keywords  Extra search terms
     */
    public function register(
        string $label,
        string $route,
        string $icon = 'Zap',
        ?string $permission = null,
        array $keywords = [],
    ): void {
        $this->items[] = [
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'permission' => $permission,
            'keywords' => $keywords,
        ];
    }

    /**
     * All registered actions with resolved URLs. Actions whose route does not
     * exist are silently skipped. Permission filtering is left to the caller.
     *
     * @return array<int, array{label: string, url: string, icon: string, permission: string|null, keywords: array<int, string>}>
     */
    public function compose(): array
    {
        $valid = array_filter($this->items, fn (array $i) => app('router')->has($i['route']));

        return array_map(fn (array $i) => [
            'label' => __($i['label']),
            'url' => route($i['route'], absolute: false),
            'icon' => $i['icon'],
            'permission' => $i['permission'],
            'keywords' => $i['keywords'],
        ], array_values($valid));
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->items;
    }
}
