<?php

namespace App\Services\Extensions\Registries;

/**
 * Aggregate entry point of the Extension SDK.
 *
 * An enabled extension's service provider receives (or resolves) this
 * registry and uses it to plug into the platform:
 *
 *   $registry->navigation()->register('Products', 'admin.store.products.index', 'Package', 'Store');
 *   $registry->widgets()->register('store.orders', 'Orders', 'stat', fn () => [...]);
 *   $registry->permissions()->register('store.manage', 'Manage Store', 'store');
 *   $registry->settings()->register('store', 'Store Settings');
 *   $registry->slots()->register(Slots::HOME_RIGHT_BOTTOM, 'StoreWidget', fn () => [...]);
 *   $registry->hooks()->listen(Hooks::USER_REGISTERED, fn (User $user) => [...]);
 */
class ExtensionRegistry
{
    public function __construct(
        private readonly NavigationRegistry $navigation,
        private readonly WidgetRegistry $widgets,
        private readonly PermissionRegistry $permissions,
        private readonly SettingsRegistry $settings,
        private readonly SlotRegistry $slots,
        private readonly HookRegistry $hooks,
        private readonly FilterRegistry $filters,
    ) {}

    public function filters(): FilterRegistry
    {
        return $this->filters;
    }

    public function navigation(): NavigationRegistry
    {
        return $this->navigation;
    }

    public function widgets(): WidgetRegistry
    {
        return $this->widgets;
    }

    public function permissions(): PermissionRegistry
    {
        return $this->permissions;
    }

    public function settings(): SettingsRegistry
    {
        return $this->settings;
    }

    public function slots(): SlotRegistry
    {
        return $this->slots;
    }

    public function hooks(): HookRegistry
    {
        return $this->hooks;
    }
}
