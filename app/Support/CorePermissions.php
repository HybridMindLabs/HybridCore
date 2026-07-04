<?php

namespace App\Support;

/**
 * Canonical list of core platform permissions.
 * Extensions register their own permissions via the PermissionRegistry —
 * nothing extension-specific belongs here.
 */
class CorePermissions
{
    /** @var array<string, array{name: string, group: string}> */
    public const ALL = [
        'admin.access' => ['name' => 'Access Admin Panel', 'group' => 'admin'],
        'users.view' => ['name' => 'View Users', 'group' => 'users'],
        'users.manage' => ['name' => 'Manage Users', 'group' => 'users'],
        'roles.view' => ['name' => 'View Roles', 'group' => 'roles'],
        'roles.manage' => ['name' => 'Manage Roles', 'group' => 'roles'],
        'settings.view' => ['name' => 'View Settings', 'group' => 'settings'],
        'settings.manage' => ['name' => 'Manage Settings', 'group' => 'settings'],
        'extensions.view' => ['name' => 'View Extensions', 'group' => 'extensions'],
        'extensions.manage' => ['name' => 'Manage Extensions', 'group' => 'extensions'],
        'themes.view' => ['name' => 'View Themes', 'group' => 'themes'],
        'themes.manage' => ['name' => 'Manage Themes', 'group' => 'themes'],
        'email.view' => ['name' => 'View Email Settings', 'group' => 'email'],
        'email.manage' => ['name' => 'Manage Email Settings', 'group' => 'email'],
        'analytics.view' => ['name' => 'View Analytics', 'group' => 'analytics'],
        'system.view' => ['name' => 'View System Info', 'group' => 'system'],
        'system.manage' => ['name' => 'Manage System', 'group' => 'system'],
        'content.view' => ['name' => 'View Content', 'group' => 'content'],
        'content.manage' => ['name' => 'Manage Content', 'group' => 'content'],
        'seo.manage' => ['name' => 'Manage SEO', 'group' => 'seo'],
        'servers.view' => ['name' => 'View Servers', 'group' => 'servers'],
        'servers.manage' => ['name' => 'Manage Servers', 'group' => 'servers'],
        'news.view' => ['name' => 'View News', 'group' => 'news'],
        'news.manage' => ['name' => 'Manage News', 'group' => 'news'],
    ];
}
