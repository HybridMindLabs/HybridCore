import {
    Crown, ShieldCheck, ShieldHalf, ShieldAlert, ShieldBan, ShieldQuestion,
    Headphones, Code2, Terminal, Bug, Wrench, Settings,
    User, Users, UserCog, UserCheck, UserStar,
    Star, Sparkles, Trophy, Award, Medal, Gem,
    Gamepad2, Swords, Target, Flame, Zap, Rocket,
    Eye, EyeOff, Lock, Unlock, Key, Fingerprint,
    Server, Database, Globe, Network, Cpu,
    Heart, HeartHandshake, Handshake, Megaphone, MessageSquare,
    Briefcase, Building2, Landmark, BadgeCheck, CircleUser,
} from '@lucide/vue';

/**
 * Curated allow-list of Lucide icon names usable for role badges and similar
 * "pick an icon" UI. Kept in sync by hand with app/Support/AvailableIcons.php —
 * both lists must contain the exact same names since they're @lucide/vue exports.
 */
export const ROLE_ICONS: Record<string, unknown> = {
    Crown, ShieldCheck, ShieldHalf, ShieldAlert, ShieldBan, ShieldQuestion,
    Headphones, Code2, Terminal, Bug, Wrench, Settings,
    User, Users, UserCog, UserCheck, UserStar,
    Star, Sparkles, Trophy, Award, Medal, Gem,
    Gamepad2, Swords, Target, Flame, Zap, Rocket,
    Eye, EyeOff, Lock, Unlock, Key, Fingerprint,
    Server, Database, Globe, Network, Cpu,
    Heart, HeartHandshake, Handshake, Megaphone, MessageSquare,
    Briefcase, Building2, Landmark, BadgeCheck, CircleUser,
};

export const ROLE_ICON_NAMES = Object.keys(ROLE_ICONS);
