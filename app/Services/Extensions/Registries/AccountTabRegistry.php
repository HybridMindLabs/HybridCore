<?php

namespace App\Services\Extensions\Registries;

/**
 * Collects extra tabs for the authenticated user's account panel (/account).
 * Each tab lives on its own route (rendered inside the account layout), mirroring
 * the core "routable" tabs (favorites, messages) — so the sidebar button simply
 * navigates to the extension's page.
 *
 *   $registry->accountTabs()->register(
 *       key: 'votes',
 *       label: 'vote::messages.nav',
 *       route: 'account.vote.index',
 *       icon: 'ThumbsUp',
 *       sort: 60,
 *   );
 */
class AccountTabRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $key  Unique tab key (used for active-state matching)
     * @param  string  $label  Display label (translation key or literal)
     * @param  string  $route  Named route the tab navigates to (must exist)
     * @param  string  $icon  Lucide icon name
     * @param  int  $sort  Sort order (lower first)
     * @param  string|null  $permission  Permission slug required; null = any authenticated user
     */
    public function register(
        string $key,
        string $label,
        string $route,
        string $icon = 'Puzzle',
        int $sort = 100,
        ?string $permission = null,
    ): void {
        $this->items[] = [
            'key' => $key,
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'sort' => $sort,
            'permission' => $permission,
        ];
    }

    /**
     * All registered tabs, sorted, with resolved URLs. Tabs whose route does not
     * exist are silently skipped. Permission filtering is left to the caller.
     *
     * @return array<int, array{key: string, label: string, url: string, icon: string, permission: string|null}>
     */
    public function compose(): array
    {
        $valid = array_filter($this->items, fn (array $i) => app('router')->has($i['route']));

        usort($valid, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn (array $i) => [
            'key' => $i['key'],
            'label' => __($i['label']),
            'url' => route($i['route'], absolute: false),
            'icon' => $i['icon'],
            'permission' => $i['permission'],
        ], array_values($valid));
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->items;
    }
}
