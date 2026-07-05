<?php

namespace App\Services\Extensions\Registries;

use Closure;

/**
 * Lets extensions contribute entries to the community activity feed on the home
 * sidebar. Each provider returns recent, pre-localized rows; the home controller
 * merges them with core activity, sorts by time and takes the newest.
 *
 *   $registry->activityFeed()->register(
 *       type: 'vote.confirmed',
 *       resolver: fn (int $limit) => Vote::with('user')->latest()->limit($limit)->get()
 *           ->map(fn ($v) => [
 *               'username' => $v->user?->username,
 *               'avatar'   => $v->user?->avatar,
 *               'text'     => trans('vote::messages.feed_voted'),
 *               'url'      => route('vote.index'),
 *               'at'       => $v->created_at,   // Carbon — used for sorting
 *           ])->all(),
 *   );
 *
 * A row is: ['username' => ?string, 'avatar' => ?string, 'text' => string,
 *            'url' => ?string, 'at' => \Carbon\CarbonInterface].
 */
class ActivityFeedRegistry
{
    /** @var array<int, array{type: string, resolver: Closure}> */
    private array $items = [];

    /**
     * @param  string  $type  Feed row type tag
     * @param  Closure(int): array<int, array<string, mixed>>  $resolver  Returns recent rows
     */
    public function register(string $type, Closure $resolver): void
    {
        $this->items[] = ['type' => $type, 'resolver' => $resolver];
    }

    /**
     * Run every provider (up to $perProvider rows each) and return the merged,
     * type-tagged rows. A provider that throws is silently skipped. Sorting and
     * trimming are left to the caller, which merges these with core activity.
     *
     * @return array<int, array<string, mixed>>
     */
    public function collect(int $perProvider = 8): array
    {
        $rows = [];
        foreach ($this->items as $provider) {
            try {
                $result = ($provider['resolver'])($perProvider);
            } catch (\Throwable) {
                continue;
            }

            foreach ((is_array($result) ? $result : []) as $row) {
                $rows[] = ['type' => $provider['type'], ...$row];
            }
        }

        return $rows;
    }

    /** @return array<int, array{type: string, resolver: Closure}> */
    public function all(): array
    {
        return $this->items;
    }
}
