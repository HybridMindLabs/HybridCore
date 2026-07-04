<?php

namespace App\Support;

/**
 * Curated allow-list of Lucide icon names usable for role badges and similar
 * "pick an icon" UI. Kept in sync by hand with resources/js/constants/icons.ts —
 * both lists must contain the exact same names since they're @lucide/vue exports.
 */
class AvailableIcons
{
    public const ROLE_ICONS = [
        'Crown', 'ShieldCheck', 'ShieldHalf', 'ShieldAlert', 'ShieldBan', 'ShieldQuestion',
        'Headphones', 'Code2', 'Terminal', 'Bug', 'Wrench', 'Settings',
        'User', 'Users', 'UserCog', 'UserCheck', 'UserStar',
        'Star', 'Sparkles', 'Trophy', 'Award', 'Medal', 'Gem',
        'Gamepad2', 'Swords', 'Target', 'Flame', 'Zap', 'Rocket',
        'Eye', 'EyeOff', 'Lock', 'Unlock', 'Key', 'Fingerprint',
        'Server', 'Database', 'Globe', 'Network', 'Cpu',
        'Heart', 'HeartHandshake', 'Handshake', 'Megaphone', 'MessageSquare',
        'Briefcase', 'Building2', 'Landmark', 'BadgeCheck', 'CircleUser',
    ];

    public static function isValid(string $icon): bool
    {
        return in_array($icon, self::ROLE_ICONS, true);
    }
}
