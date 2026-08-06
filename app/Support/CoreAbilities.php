<?php

namespace App\Support;

/**
 * Canonical list of core-baseline API-token abilities. Only covers api/v1
 * endpoints that are already authenticated (auth:sanctum) today — currently
 * public endpoints (servers, users/{username}) are deliberately not here,
 * gating them would be a breaking change to anonymous public API access.
 * Extensions register their own abilities via the AbilityRegistry —
 * nothing extension-specific belongs in this class.
 */
class CoreAbilities
{
    /** @var array<string, array{label: string, group: string, description: string}> */
    public const ALL = [
        'notifications:read' => [
            'label' => 'Read Notifications',
            'group' => 'notifications',
            'description' => "List a user's notifications.",
        ],
        'notifications:write' => [
            'label' => 'Manage Notifications',
            'group' => 'notifications',
            'description' => 'Mark notifications as read or delete them.',
        ],
    ];
}
