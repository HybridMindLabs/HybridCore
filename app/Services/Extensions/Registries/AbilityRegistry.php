<?php

namespace App\Services\Extensions\Registries;

/**
 * Collects Sanctum ability strings from core and enabled extensions so the
 * admin API-token form can render a checklist without core ever knowing
 * which extensions exist. Mirrors PermissionRegistry's shape exactly.
 */
class AbilityRegistry
{
    /** @var array<string, array{label: string, group: string, description: string|null}> */
    private array $abilities = [];

    public function register(string $key, string $label, string $group = 'general', ?string $description = null): void
    {
        $this->abilities[$key] = ['label' => $label, 'group' => $group, 'description' => $description];
    }

    /** @param array<string, array{label: string, group: string, description?: string|null}> $abilities */
    public function registerMany(array $abilities): void
    {
        foreach ($abilities as $key => $def) {
            $this->register($key, $def['label'], $def['group'], $def['description'] ?? null);
        }
    }

    /** @return array<string, array{label: string, group: string, description: string|null}> */
    public function all(): array
    {
        return $this->abilities;
    }
}
