<?php

namespace App\Services\Extensions\Registries;

/**
 * Collects admin navigation items from core and enabled extensions.
 * The sidebar is composed from this registry at request time.
 */
class NavigationRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $label  Display label
     * @param  string  $route  Named route (must exist)
     * @param  string  $icon  Lucide icon name (e.g. "Server")
     * @param  string|null  $section  Group heading (e.g. "Gaming"); null = top section
     * @param  string|null  $permission  Permission slug required to see the item
     * @param  int  $sort  Sort order within section (lower first)
     * @param  string|null  $activePattern  URL prefix used for active state (defaults to route path)
     */
    public function register(
        string $label,
        string $route,
        string $icon,
        ?string $section = null,
        ?string $permission = null,
        int $sort = 100,
        ?string $activePattern = null,
    ): void {
        $this->items[] = [
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'section' => $section,
            'permission' => $permission,
            'sort' => $sort,
            'activePattern' => $activePattern,
        ];
    }

    /**
     * All items grouped by section, sorted, with resolved URLs.
     * Items whose route does not exist are silently skipped.
     *
     * @return array<int, array{heading: string|null, items: array<int, array<string, mixed>>}>
     */
    public function compose(): array
    {
        $valid = array_filter($this->items, fn (array $i) => app('router')->has($i['route']));

        usort($valid, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        $sections = [];
        foreach ($valid as $item) {
            $key = $item['section'] ?? '';
            $url = route($item['route'], absolute: false);
            $sections[$key]['heading'] = $item['section'] !== null ? __($item['section']) : null;
            $sections[$key]['items'][] = [
                'label' => __($item['label']),
                'url' => $url,
                'icon' => $item['icon'],
                'permission' => $item['permission'],
                'activePattern' => $item['activePattern'] ?? $url,
            ];
        }

        return array_values($sections);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->items;
    }
}
