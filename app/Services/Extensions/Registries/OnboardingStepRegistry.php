<?php

namespace App\Services\Extensions\Registries;

use Closure;

/**
 * Lets extensions add a step to the post-registration onboarding wizard. Each
 * step is a self-contained global slot component rendered as an extra slide
 * after the core steps — informational or link-out by nature (any data it needs
 * to persist, it saves through its own routes, not the core complete() payload).
 *
 *   $registry->onboardingSteps()->register(
 *       key:       'vote_intro',
 *       component: 'HybridcoreVoteOnboarding',
 *       title:     'vote::messages.onboarding_title',
 *       icon:      'ThumbsUp',
 *       sort:      80,
 *       when:      fn () => VoteSite::where('is_active', true)->exists(),
 *   );
 */
class OnboardingStepRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * @param  string  $key  Unique step key
     * @param  string  $component  Global slot component name for the slide body
     * @param  string  $title  Step title (translation key or literal)
     * @param  string  $icon  Lucide icon name for the stepper
     * @param  int  $sort  Order among extension steps (lower first)
     * @param  (Closure(): bool)|null  $when  Guard — step is skipped when it returns false
     */
    public function register(
        string $key,
        string $component,
        string $title,
        string $icon = 'Sparkles',
        int $sort = 100,
        ?Closure $when = null,
    ): void {
        $this->items[] = [
            'key' => $key,
            'component' => $component,
            'title' => $title,
            'icon' => $icon,
            'sort' => $sort,
            'when' => $when,
        ];
    }

    /**
     * Steps whose guard passes, sorted, with translated titles. A guard that
     * throws is treated as false — a broken step must not break onboarding.
     *
     * @return array<int, array{key: string, component: string, title: string, icon: string}>
     */
    public function compose(): array
    {
        $sorted = $this->items;
        usort($sorted, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        $result = [];
        foreach ($sorted as $item) {
            try {
                if ($item['when'] !== null && ! ($item['when'])()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $result[] = [
                'key' => $item['key'],
                'component' => $item['component'],
                'title' => __($item['title']),
                'icon' => $item['icon'],
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
