<?php

namespace App\Services\Extensions\Registries;

use Closure;
use Throwable;

/**
 * Registers frontend Vue components into named page slots.
 *
 * Extensions call:
 *   $registry->slots()->register(Slots::HOME_RIGHT_BOTTOM, 'MyWidget', fn() => ['count' => 5]);
 *
 * The composed output is shared via Inertia so every page can render
 * <ExtensionSlot name="home.right.bottom" /> without knowing what's inside.
 */
class SlotRegistry
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $slots = [];

    /**
     * @param  string  $slot  Slot identifier — use Slots:: constants
     * @param  string  $component  Global Vue component name registered in app.ts
     * @param  Closure|null  $data  Lazy props resolver — called only when the slot is rendered
     * @param  string|null  $permission  Optional permission gate (null = visible to all)
     * @param  int  $priority  Lower renders first
     */
    public function register(
        string $slot,
        string $component,
        ?Closure $data = null,
        ?string $permission = null,
        int $priority = 100,
    ): void {
        $this->slots[$slot][] = compact('component', 'data', 'permission', 'priority');
    }

    public function unregister(string $slot, string $component): void
    {
        if (! isset($this->slots[$slot])) {
            return;
        }

        $this->slots[$slot] = array_values(
            array_filter($this->slots[$slot], fn (array $e) => $e['component'] !== $component)
        );
    }

    /**
     * Resolve all slots, evaluate data closures, sort by priority.
     * A slot whose data closure throws is skipped — never breaks the page.
     *
     * @return array<string, list<array{component: string, props: array<string, mixed>, priority: int, permission: string|null}>>
     */
    public function compose(): array
    {
        $result = [];

        foreach ($this->slots as $name => $entries) {
            $resolved = [];

            foreach ($entries as $entry) {
                try {
                    $props = $entry['data'] instanceof Closure ? ($entry['data'])() : [];
                } catch (Throwable) {
                    continue;
                }

                // null return from the data closure means "skip this entry"
                // (useful for conditional rendering based on settings).
                if ($props === null) {
                    continue;
                }

                $resolved[] = [
                    'component' => $entry['component'],
                    'props' => $props,
                    'priority' => $entry['priority'],
                    'permission' => $entry['permission'],
                ];
            }

            usort($resolved, fn (array $a, array $b) => $a['priority'] <=> $b['priority']);

            if ($resolved !== []) {
                $result[$name] = $resolved;
            }
        }

        return $result;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function all(): array
    {
        return $this->slots;
    }

    /** True if any component is registered for the given slot. */
    public function has(string $slot): bool
    {
        return isset($this->slots[$slot]) && $this->slots[$slot] !== [];
    }
}
