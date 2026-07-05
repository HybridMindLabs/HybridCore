<?php

namespace App\Services\Extensions\Registries;

use App\Models\User;
use Closure;

/**
 * Collects extra panels for the public user profile (/u/{username}). Each panel
 * is rendered by a globally-registered slot component (same auto-registration as
 * SlotRegistry — a Vue file named after `component`), fed props from `data`.
 *
 *   $registry->profileTabs()->register(
 *       key: 'votes',
 *       label: 'vote::messages.top_voters',
 *       icon: 'Trophy',
 *       component: 'HybridcoreVoteProfilePanel',
 *       data: fn (User $user) => ['votes' => app(VoteService::class)->userVoteCount($user)],
 *       sort: 50,
 *   );
 */
class ProfileTabRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $key  Unique panel key
     * @param  string  $label  Display label (translation key or literal)
     * @param  string  $component  Globally-registered slot component name
     * @param  Closure(User): array<string, mixed>  $data  Props provider, given the profile user
     * @param  string  $icon  Lucide icon name
     * @param  int  $sort  Sort order (lower first)
     */
    public function register(
        string $key,
        string $label,
        string $component,
        Closure $data,
        string $icon = 'Puzzle',
        int $sort = 100,
    ): void {
        $this->items[] = [
            'key' => $key,
            'label' => $label,
            'component' => $component,
            'data' => $data,
            'icon' => $icon,
            'sort' => $sort,
        ];
    }

    /**
     * Resolve every panel for the given profile user. A panel whose data provider
     * throws is silently skipped — a broken extension must not break the profile.
     *
     * @return array<int, array{key: string, label: string, icon: string, component: string, props: array<string, mixed>}>
     */
    public function compose(User $user): array
    {
        $sorted = $this->items;
        usort($sorted, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        $result = [];
        foreach ($sorted as $item) {
            try {
                $props = ($item['data'])($user);
            } catch (\Throwable) {
                continue;
            }

            $result[] = [
                'key' => $item['key'],
                'label' => __($item['label']),
                'icon' => $item['icon'],
                'component' => $item['component'],
                'props' => is_array($props) ? $props : [],
            ];
        }

        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        return $this->items;
    }
}
