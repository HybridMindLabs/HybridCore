<?php

namespace App\Services\Extensions\Registries;

use Closure;
use Illuminate\Support\Str;
use Throwable;

/**
 * Block types the page builder offers, from core and enabled extensions.
 *
 * A block is described by its fields; the editor builds its form from them and
 * every save is cleaned against them, so stored block data never holds keys or
 * values a block did not declare. A block can also resolve live data at render
 * time (servers, news, products) through its resolver.
 *
 * Field shape: ['key' => 'title', 'type' => 'text', 'label' => 'Title', ...]
 *   text, textarea  string, optional 'max' length
 *   markdown        string rendered to safe HTML by the block's resolver
 *   url, image      http(s) or site-relative URL, anything else is dropped
 *   number          integer, optional 'min' / 'max'
 *   select          one of 'options' (value => label)
 *   toggle          boolean
 *   game            a game id, or null for every game
 *   list            repeated group of 'fields', optional 'max_items'
 * Any field may carry a 'default'.
 */
class PageBlockRegistry
{
    public const MAX_BLOCKS = 100;

    private const MAX_ITEMS = 24;

    /** @var array<string, array<string, mixed>> */
    private array $blocks = [];

    /**
     * @param  string  $type  Unique block type, vendor-prefixed for extensions ("hybridcore-shop.products")
     * @param  string  $component  Vue component that renders it on the public page
     * @param  array<int, array<string, mixed>>  $fields
     * @param  Closure|null  $resolve  fn (array $data): array — extra props for the component
     */
    public function register(
        string $type,
        string $label,
        string $component,
        array $fields = [],
        ?Closure $resolve = null,
        string $icon = 'Square',
        string $category = 'content',
        int $sort = 100,
        string $description = '',
    ): void {
        $this->blocks[$type] = compact('type', 'label', 'component', 'fields', 'resolve', 'icon', 'category', 'sort', 'description');
    }

    public function unregister(string $type): void
    {
        unset($this->blocks[$type]);
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    /**
     * Block types for the editor, without resolvers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        $blocks = array_values($this->blocks);
        usort($blocks, fn (array $a, array $b) => [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']]);

        return array_map(fn (array $b) => [
            'type' => $b['type'],
            'label' => $b['label'],
            'description' => $b['description'],
            'icon' => $b['icon'],
            'category' => $b['category'],
            'fields' => $b['fields'],
            'defaults' => $this->clean($b['fields'], []),
        ], $blocks);
    }

    /**
     * Clean submitted blocks: data is reduced to the declared fields and every
     * block keeps a stable id.
     *
     * Blocks whose type is not registered right now (their extension is
     * disabled) are dropped — unless $keepUnknown, which saving uses so that
     * disabling an extension and editing a page does not wipe its blocks. They
     * are kept as they were and never rendered until the type exists again.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<int, array{id: string, type: string, data: array<string, mixed>}>
     */
    public function sanitize(array $blocks, bool $keepUnknown = false): array
    {
        $clean = [];
        foreach (array_slice(array_values($blocks), 0, self::MAX_BLOCKS) as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || ! preg_match('/^[a-z0-9._-]{1,100}$/i', $block['type'])) {
                continue;
            }

            $known = $this->has($block['type']);
            if (! $known && ! $keepUnknown) {
                continue;
            }

            $id = is_string($block['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $block['id'])
                ? $block['id']
                : Str::lower(Str::random(12));
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            // Kept unchecked, so at least kept small.
            if (! $known && strlen((string) json_encode($data)) > 65535) {
                $data = [];
            }

            $clean[] = [
                'id' => $id,
                'type' => $block['type'],
                'data' => $known ? $this->clean($this->blocks[$block['type']]['fields'], $data) : $data,
            ];
        }

        return $clean;
    }

    /**
     * Blocks ready for the public page. A block whose type is gone or whose
     * resolver throws is left out rather than breaking the page.
     *
     * @param  array<int, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function render(array $blocks): array
    {
        $rendered = [];
        foreach ($this->sanitize($blocks) as $block) {
            $definition = $this->blocks[$block['type']];

            try {
                $props = $definition['resolve'] instanceof Closure ? ($definition['resolve'])($block['data']) : [];
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $rendered[] = [
                'id' => $block['id'],
                'type' => $block['type'],
                'component' => $definition['component'],
                'data' => $block['data'],
                'props' => $props,
            ];
        }

        return $rendered;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $out[$key] = $this->cleanValue($field, $data[$key] ?? ($field['default'] ?? null));
        }

        return $out;
    }

    /** @param  array<string, mixed>  $field */
    private function cleanValue(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'text' => $this->string($value, $field['max'] ?? 255),
            'textarea' => $this->string($value, $field['max'] ?? 2000),
            'markdown' => $this->string($value, $field['max'] ?? 20000),
            'url', 'image' => $this->url($value),
            'number' => $this->number($field, $value),
            'select' => array_key_exists((string) $value, $field['options'] ?? [])
                ? (string) $value
                : (string) ($field['default'] ?? array_key_first($field['options'] ?? []) ?? ''),
            'toggle' => filter_var($value, FILTER_VALIDATE_BOOL),
            'game' => is_numeric($value) && (int) $value > 0 ? (int) $value : null,
            'list' => array_values(array_map(
                fn ($item) => $this->clean($field['fields'] ?? [], is_array($item) ? $item : []),
                array_slice(is_array($value) ? array_values($value) : [], 0, $field['max_items'] ?? self::MAX_ITEMS),
            )),
            default => null,
        };
    }

    private function string(mixed $value, int $max): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, $max) : '';
    }

    /** Only http(s) and site-relative links survive — no javascript:, data: or protocol-relative URLs. */
    private function url(mixed $value): string
    {
        $url = $this->string($value, 2048);

        return preg_match('#^(https?://|/(?!/))#i', $url) ? $url : '';
    }

    /** @param  array<string, mixed>  $field */
    private function number(array $field, mixed $value): int
    {
        $number = is_numeric($value) ? (int) $value : (int) ($field['default'] ?? 0);

        return max($field['min'] ?? PHP_INT_MIN, min($field['max'] ?? PHP_INT_MAX, $number));
    }
}
