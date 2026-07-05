<?php

namespace App\Services\Extensions\Registries;

/**
 * Collects links for the public site footer's navigation column. Mirrors
 * PublicNavigationRegistry, but rendered in the footer rather than the header.
 *
 *   $registry->footerLinks()->register(
 *       label: 'vote::messages.nav',
 *       route: 'vote.index',
 *       sort: 60,
 *   );
 */
class FooterLinkRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $label  Display label (translation key or literal)
     * @param  string  $route  Named route (must exist to be shown)
     * @param  int  $sort  Sort order (lower first)
     * @param  string|null  $permission  Permission slug required; null = everyone
     */
    public function register(
        string $label,
        string $route,
        int $sort = 100,
        ?string $permission = null,
    ): void {
        $this->items[] = [
            'label' => $label,
            'route' => $route,
            'sort' => $sort,
            'permission' => $permission,
        ];
    }

    /**
     * All registered links, sorted, with resolved URLs. Links whose route does
     * not exist are silently skipped. Permission filtering is left to the caller.
     *
     * @return array<int, array{label: string, url: string, permission: string|null}>
     */
    public function compose(): array
    {
        $valid = array_filter($this->items, fn (array $i) => app('router')->has($i['route']));

        usort($valid, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn (array $i) => [
            'label' => __($i['label']),
            'url' => route($i['route'], absolute: false),
            'permission' => $i['permission'],
        ], array_values($valid));
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->items;
    }
}
