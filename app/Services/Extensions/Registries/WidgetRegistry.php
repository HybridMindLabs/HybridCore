<?php

namespace App\Services\Extensions\Registries;

use Closure;
use Throwable;

/**
 * Collects admin dashboard widgets from core and enabled extensions.
 */
class WidgetRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $widgets = [];

    /**
     * @param  string  $id  Unique widget id (e.g. "core.users-count")
     * @param  string  $title  Display title
     * @param  string  $component  Frontend widget type ("stat", "status", or a custom Vue component name)
     * @param  Closure|null  $data  Callback resolving widget props at render time
     * @param  string|null  $permission  Permission slug required to see the widget
     * @param  int  $sort  Sort order (lower first)
     */
    public function register(
        string $id,
        string $title,
        string $component,
        ?Closure $data = null,
        ?string $permission = null,
        int $sort = 100,
    ): void {
        $this->widgets[$id] = [
            'id' => $id,
            'title' => $title,
            'component' => $component,
            'data' => $data,
            'permission' => $permission,
            'sort' => $sort,
        ];
    }

    public function unregister(string $id): void
    {
        unset($this->widgets[$id]);
    }

    /**
     * Resolved widgets ready for the frontend, sorted.
     * A widget whose data callback throws is skipped (never breaks the dashboard).
     *
     * @return array<int, array<string, mixed>>
     */
    public function compose(): array
    {
        $widgets = array_values($this->widgets);
        usort($widgets, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        $resolved = [];
        foreach ($widgets as $widget) {
            try {
                $props = $widget['data'] instanceof Closure ? ($widget['data'])() : [];
            } catch (Throwable) {
                continue;
            }

            $resolved[] = [
                'id' => $widget['id'],
                'title' => $widget['title'],
                'component' => $widget['component'],
                'permission' => $widget['permission'],
                'props' => $props,
            ];
        }

        return $resolved;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->widgets;
    }
}
