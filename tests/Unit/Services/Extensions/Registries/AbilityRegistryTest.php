<?php

namespace Tests\Unit\Services\Extensions\Registries;

use App\Services\Extensions\Registries\AbilityRegistry;
use App\Support\CoreAbilities;
use Tests\TestCase;

class AbilityRegistryTest extends TestCase
{
    public function test_register_and_registermany_populate_all(): void
    {
        $registry = new AbilityRegistry;

        $registry->register('store:read', 'Read Store', 'store');
        $registry->registerMany(CoreAbilities::ALL);

        $all = $registry->all();

        $this->assertSame(['label' => 'Read Store', 'group' => 'store'], $all['store:read']);
        $this->assertArrayHasKey('notifications:read', $all);
        $this->assertArrayHasKey('notifications:write', $all);
    }

    public function test_core_abilities_are_registered_via_the_extension_registry_boot(): void
    {
        $abilities = app(\App\Services\Extensions\Registries\ExtensionRegistry::class)->abilities()->all();

        $this->assertArrayHasKey('notifications:read', $abilities);
        $this->assertArrayHasKey('notifications:write', $abilities);
    }
}
