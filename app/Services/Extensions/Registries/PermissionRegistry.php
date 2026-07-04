<?php

namespace App\Services\Extensions\Registries;

use App\Models\Permission;
use Illuminate\Support\Facades\Schema;

/**
 * Collects permissions from core and enabled extensions and syncs
 * them into the permissions table.
 */
class PermissionRegistry
{
    /** @var array<string, array{name: string, group: string}> */
    private array $permissions = [];

    public function register(string $slug, string $name, string $group = 'general'): void
    {
        $this->permissions[$slug] = ['name' => $name, 'group' => $group];
    }

    /** @param array<string, array{name: string, group: string}> $permissions */
    public function registerMany(array $permissions): void
    {
        foreach ($permissions as $slug => $def) {
            $this->register($slug, $def['name'], $def['group'] ?? 'general');
        }
    }

    /** @return array<string, array{name: string, group: string}> */
    public function all(): array
    {
        return $this->permissions;
    }

    /**
     * Upsert all registered permissions into the database.
     * Existing rows keep their id (role links stay intact).
     *
     * @return int Number of permissions synced.
     */
    public function sync(): int
    {
        if (! Schema::hasTable('permissions')) {
            return 0;
        }

        $count = 0;
        foreach ($this->permissions as $slug => $def) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $def['name'], 'group' => $def['group']],
            );
            $count++;
        }

        return $count;
    }
}
