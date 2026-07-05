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
        private readonly PublicNavigationRegistry $publicNavigation,
        private readonly AccountTabRegistry $accountTabs,
        private readonly ProfileTabRegistry $profileTabs,
        private readonly UserMenuRegistry $userMenu,
        private readonly SearchProviderRegistry $search,
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

    /** Public (site header) navigation — visible to visitors, not just admins. */
    public function publicNavigation(): PublicNavigationRegistry
    {
        return $this->publicNavigation;
    }

    /** Tabs in the authenticated user's account panel (/account). */
    public function accountTabs(): AccountTabRegistry
    {
        return $this->accountTabs;
    }

    /** Panels on the public user profile (/u/{username}). */
    public function profileTabs(): ProfileTabRegistry
    {
        return $this->profileTabs;
    }

    /** Items in the top-right user dropdown menu. */
    public function userMenu(): UserMenuRegistry
    {
        return $this->userMenu;
    }

    /** Providers for the global search endpoint. */
    public function search(): SearchProviderRegistry
    {
        return $this->search;
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
