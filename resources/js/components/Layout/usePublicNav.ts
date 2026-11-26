import { usePage } from '@inertiajs/vue3';
import {
    BookOpen, Gift, Home, Link as LinkIcon, Package, Phone, Server, ShoppingCart, Star, ThumbsUp, Trophy,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';

export interface MenuItem { label: string; url: string; target: string; children: MenuItem[] }
export interface PublicNavItem { label: string; url: string; icon: string }

export interface PublicUser {
    id?: number; name: string; username: string | null; avatar: string | null; verified: boolean;
    two_factor_enabled: boolean; is_admin: boolean; can_access_admin: boolean;
}

export interface SharedProps {
    auth: { user: PublicUser | null } | null;
    registrationEnabled: boolean;
    impersonating: { name: string } | null;
    app: { name: string };
    menus: Record<string, MenuItem[]>;
    [key: string]: unknown;
}

const extensionIcons: Record<string, Component> = { ThumbsUp, Trophy, Gift, Package, Server, BookOpen, Star, ShoppingCart };

/** An icon an extension asked for by name, or a plain link icon. */
export function publicNavIcon(name: string): Component {
    return extensionIcons[name] ?? LinkIcon;
}

/** Path of an href, without trailing slash. The base only lets relative hrefs parse (SSR has no window). */
function pathOf(href: string): string {
    try {
        return new URL(href, 'http://localhost').pathname.replace(/\/+$/, '') || '/';
    } catch {
        return href;
    }
}

/** The site's own links, extension links and which of them is the current page. */
export function usePublicNav() {
    const page = usePage<SharedProps>();

    const navLinks = [
        { key: 'nav_home', href: route('home'), icon: Home },
        { key: 'nav_servers', href: route('servers.index'), icon: Server },
        { key: 'nav_rules', href: route('rules.index'), icon: BookOpen },
        { key: 'nav_contacts', href: route('contact.show'), icon: Phone },
    ];

    const currentPath = computed(() => (page.url || '/').split('?')[0].replace(/\/+$/, '') || '/');

    /** Home only matches exactly; every other section also matches its children (/servers/cs2 → Servers). */
    function isActive(href: string): boolean {
        const target = pathOf(href);
        return target === '/'
            ? currentPath.value === '/'
            : currentPath.value === target || currentPath.value.startsWith(`${target}/`);
    }

    const publicNav = computed(() => (page.props.publicNav as PublicNavItem[] | undefined) ?? []);
    const userMenu = computed(() => (page.props.userMenu as PublicNavItem[] | undefined) ?? []);
    const headerMenu = computed(() => page.props.menus?.header ?? []);

    return { navLinks, isActive, publicNav, userMenu, headerMenu };
}
