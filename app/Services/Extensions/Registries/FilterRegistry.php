<?php

namespace App\Services\Extensions\Registries;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WordPress-style value filters: hooks notify, filters transform.
 *
 * Core (or another extension) sends a value through a named filter and gets
 * back the — possibly modified — value:
 *
 *   $value = $registry->filters()->apply(Filters::INERTIA_SHARED, $shared);
 *
 * Extensions register transformers:
 *
 *   $registry->filters()->add(Filters::INERTIA_SHARED, function (array $shared) {
 *       $shared['storeCart'] = CartService::summary();
 *       return $shared;
 *   });
 *
 * A throwing callback is logged and skipped — it never breaks the value chain.
 */
class FilterRegistry
{
    /** @var array<string, list<array{callback: callable, priority: int}>> */
    private array $filters = [];

    /** Lower priority runs first. */
    public function add(string $name, callable $callback, int $priority = 100): void
    {
        $this->filters[$name][] = ['callback' => $callback, 'priority' => $priority];

        usort($this->filters[$name], fn (array $a, array $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Pass $value through every callback registered for $name.
     * Extra $args are passed to each callback after the value (read-only context).
     */
    public function apply(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->filters[$name] ?? [] as $entry) {
            try {
                $value = ($entry['callback'])($value, ...$args);
            } catch (Throwable $e) {
                Log::warning("Extension filter '{$name}' threw and was skipped", [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $value;
    }

    public function has(string $name): bool
    {
        return isset($this->filters[$name]) && $this->filters[$name] !== [];
    }
}
