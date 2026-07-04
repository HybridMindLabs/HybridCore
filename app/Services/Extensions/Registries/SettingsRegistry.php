<?php

namespace App\Services\Extensions\Registries;

/**
 * Collects extension settings pages so the admin Settings area can
 * link to them without the core hardcoding any extension.
 *
 * URL convention:   /admin/settings/extensions/{slug}
 * Route convention: admin.settings.extensions.{slug}
 *
 * Extensions only declare their slug — the URL is derived automatically.
 * Pass $route to override (backwards-compatible with older extensions).
 */
class SettingsRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $pages = [];

    /**
     * @param  string  $slug  Unique slug — also determines the URL segment
     * @param  string  $label  Display label shown in the settings sidebar
     * @param  string|null  $route  Named route override (default: admin.settings.extensions.{slug})
     * @param  string|null  $permission  Permission required to access this settings page
     * @param  int  $sort  Sort order within the settings sidebar
     */
    public function register(
        string $slug,
        string $label,
        ?string $route = null,
        ?string $permission = null,
        int $sort = 100,
    ): void {
        $this->pages[$slug] = [
            'slug' => $slug,
            'label' => $label,
            'route' => $route ?? "admin.settings.extensions.{$slug}",
            'permission' => $permission,
            'sort' => $sort,
        ];
    }

    /**
     * Sorted pages with resolved URLs, skipping any whose route does not exist.
     *
     * @return list<array{slug: string, label: string, url: string, permission: string|null}>
     */
    public function compose(): array
    {
        $pages = array_filter(
            array_values($this->pages),
            fn (array $p) => app('router')->has($p['route']),
        );

        usort($pages, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_values(array_map(fn (array $p) => [
            'slug' => $p['slug'],
            'label' => $p['label'],
            'url' => route($p['route'], absolute: false),
            'permission' => $p['permission'],
        ], $pages));
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->pages;
    }
}
