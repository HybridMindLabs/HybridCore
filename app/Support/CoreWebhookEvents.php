<?php

namespace App\Support;

/**
 * Human labels/groups for every core Hooks:: event, so the Admin > Webhooks
 * event picker can show "New user registered" instead of the raw
 * "user.registered" string. Keys must match Hooks::all() exactly — this is
 * a display layer over those constants, not a separate event list.
 * Extensions register their own via WebhookEventRegistry directly.
 */
class CoreWebhookEvents
{
    /** @var array<string, array{label: string, group: string}> */
    public const ALL = [
        Hooks::USER_REGISTERED => ['label' => 'New user registered', 'group' => 'Users'],
        Hooks::USER_BANNED => ['label' => 'User banned', 'group' => 'Users'],
        Hooks::USER_LOGIN => ['label' => 'User logged in', 'group' => 'Users'],
        Hooks::USER_FOLLOWED => ['label' => 'User followed another user', 'group' => 'Users'],
        Hooks::USER_ANONYMIZED => ['label' => 'User account deleted', 'group' => 'Users'],
        Hooks::COMMENT_CREATED => ['label' => 'News comment posted', 'group' => 'Content'],
        Hooks::REVIEW_CREATED => ['label' => 'Server review posted', 'group' => 'Content'],
        Hooks::ARTICLE_PUBLISHED => ['label' => 'News article published', 'group' => 'Content'],
        Hooks::MESSAGE_SENT => ['label' => 'Private message sent', 'group' => 'Content'],
        Hooks::SERVER_QUERIED => ['label' => 'Server query completed', 'group' => 'Servers'],
        Hooks::EXTENSION_ENABLED => ['label' => 'Extension enabled', 'group' => 'Extensions'],
        Hooks::EXTENSION_DISABLED => ['label' => 'Extension disabled', 'group' => 'Extensions'],
        Hooks::EXTENSION_UPDATED => ['label' => 'Extension updated', 'group' => 'Extensions'],
        Hooks::EXTENSION_UNINSTALLED => ['label' => 'Extension uninstalled', 'group' => 'Extensions'],
    ];
}
