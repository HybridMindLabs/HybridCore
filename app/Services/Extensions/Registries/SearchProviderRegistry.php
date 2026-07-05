<?php

namespace App\Services\Extensions\Registries;

use Closure;

/**
 * Collects global-search providers from enabled extensions. The search endpoint
 * runs core (users, servers) results first, then each registered provider,
 * grouping matches under the provider's label.
 *
 *   $registry->search()->register(
 *       key: 'vote_sites',
 *       label: 'vote::messages.nav',
 *       icon: 'ThumbsUp',
 *       resolver: fn (string $term, int $limit) => [
 *           ['title' => 'TopG', 'url' => '/vote', 'meta' => '10 pts'],
 *       ],
 *   );
 *
 * A resolver returns a list of rows: ['title' => string, 'url' => string, 'meta' => ?string].
 */
class SearchProviderRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $key  Unique provider key
     * @param  string  $label  Group heading (translation key or literal)
     * @param  Closure(string, int): array<int, array<string, mixed>>  $resolver  Returns matches for a term
     * @param  string  $icon  Lucide icon name for the group
     * @param  string|null  $permission  Permission slug required; null = everyone
     */
    public function register(
        string $key,
        string $label,
        Closure $resolver,
        string $icon = 'Search',
        ?string $permission = null,
    ): void {
        $this->items[$key] = [
            'key' => $key,
            'label' => $label,
            'resolver' => $resolver,
            'icon' => $icon,
            'permission' => $permission,
        ];
    }

    /**
     * Registered providers (including their resolver closures). Permission
     * filtering and resolver execution are the caller's responsibility, since
     * they require the request user and the search term.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->items);
    }
}
