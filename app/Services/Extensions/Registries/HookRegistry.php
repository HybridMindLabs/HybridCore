<?php

namespace App\Services\Extensions\Registries;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lightweight action-hook bus for extensions.
 *
 * Extensions call, from their ServiceProvider::boot():
 *   $registry->hooks()->listen(Hooks::USER_REGISTERED, fn (User $user) => ...);
 *
 * Core fires hooks at meaningful lifecycle points — see App\Support\Hooks for
 * the full catalog and argument signatures. Firing never throws: a listener
 * that throws is logged and skipped, every other listener still runs.
 */
class HookRegistry
{
    /** @var array<string, list<array{callback: Closure, priority: int}>> */
    private array $listeners = [];

    public function listen(string $hook, Closure $callback, int $priority = 100): void
    {
        $this->listeners[$hook][] = compact('callback', 'priority');
    }

    /** Call every listener registered for $hook, in priority order (lower first). */
    public function fire(string $hook, mixed ...$args): void
    {
        if (empty($this->listeners[$hook])) {
            return;
        }

        $entries = $this->listeners[$hook];
        usort($entries, fn (array $a, array $b) => $a['priority'] <=> $b['priority']);

        foreach ($entries as $entry) {
            try {
                ($entry['callback'])(...$args);
            } catch (Throwable $e) {
                Log::warning('Extension hook listener failed', [
                    'hook' => $hook,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    public function has(string $hook): bool
    {
        return ! empty($this->listeners[$hook]);
    }

    /** @return array<string, int> Hook name => listener count, for diagnostics. */
    public function summary(): array
    {
        return array_map(fn (array $entries) => count($entries), $this->listeners);
    }
}
