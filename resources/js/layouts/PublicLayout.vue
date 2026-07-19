<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Menu, X, UserCircle, Users, User, LogOut, BadgeCheck,
    Home, Server, BookOpen, Phone, Star, ShieldCheck,
    ChevronDown, Check, Moon, Sun, Bell,
    ThumbsUp, Trophy, Gift, Package, Link as LinkIcon, ArrowRight,
} from '@lucide/vue';
import type { Component } from 'vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { useDesktopNotifications } from '@/composables/useDesktopNotifications';
import ToastManager from '@/components/UI/ToastManager.vue';
import FlagIcon from '@/components/UI/FlagIcon.vue';
import SocialLinks from '@/components/UI/SocialLinks.vue';
import { useFlashToast } from '@/composables/useFlashToast';

const { t, currentLocale, supportedLocales, switcherEnabled, isCurrentLocale, switchLocale } = useLocale();
const { theme, toggle: toggleTheme, init: initTheme } = useTheme();
const { ensurePermission, notifyFromData } = useDesktopNotifications();

useFlashToast();

interface MenuItem { label: string; url: string; target: string; children: MenuItem[] }
interface SharedProps {
    auth: { user: { name: string; username: string | null; avatar: string | null; verified: boolean; two_factor_enabled: boolean; is_admin: boolean } | null };
    impersonating: { name: string } | null;
    app: { name: string };
    menus: Record<string, MenuItem[]>;
    [key: string]: unknown;
}

const page = usePage<SharedProps>();

const headerMenuItems  = computed(() => page.props.menus?.header      ?? []);
const footerNavItems   = computed(() => page.props.menus?.footer_links ?? []);
const footerLegalLinks = computed(() => page.props.menus?.footer_legal ?? []);
const legalPages = computed(() => (page.props.legalPages as { slug: string; title: string }[] | undefined) ?? []);

const mobileOpen = ref(false);
const langOpen   = ref(false);
const userOpen   = ref(false);
const notifOpen  = ref(false);
const langRef    = ref<HTMLElement | null>(null);
const userRef    = ref<HTMLElement | null>(null);
const notifRef   = ref<HTMLElement | null>(null);

const unreadCount = ref(0);
let echoChannel: ReturnType<typeof window.Echo.private> | null = null;

async function fetchUnread() {
    if (!page.props.auth?.user) return;
    try {
        const r = await fetch('/api/notifications/unread-count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (r.ok) {
            const json = await r.json();
            unreadCount.value = json.count ?? 0;
        }
    } catch {}
}

interface RecentNotif { id: string; type: string; data: Record<string, unknown>; read: boolean; created_at: string }
const recentNotifications = ref<RecentNotif[]>([]);
const notifLoading = ref(false);
const markingAll = ref(false);

async function toggleNotifDropdown() {
    ensurePermission();
    notifOpen.value = !notifOpen.value;
    if (notifOpen.value) await fetchRecentNotifications();
}

async function fetchRecentNotifications() {
    notifLoading.value = true;
    try {
        const r = await fetch('/api/notifications/recent', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (r.ok) {
            const json = await r.json();
            recentNotifications.value = json.notifications ?? [];
        }
    } finally {
        notifLoading.value = false;
    }
}

function notifLabel(n: RecentNotif): string {
    if (n.type === 'new_message') return `New message from ${n.data.sender_username ?? 'someone'}`;
    return (n.data.message as string | undefined) ?? (n.data.preview as string | undefined) ?? 'New notification';
}

async function markAllRead() {
    if (markingAll.value || unreadCount.value === 0) return;
    markingAll.value = true;
    try {
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        await fetch(route('account.notifications.read-all'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        unreadCount.value = 0;
        recentNotifications.value = recentNotifications.value.map((n) => ({ ...n, read: true }));
    } finally {
        markingAll.value = false;
    }
}

const dark = computed(() => theme.value === 'dark');

onMounted(() => {
    initTheme();
    document.addEventListener('click', onDocClick, true);
    fetchUnread();

    // Real-time notification count via Reverb
    const userId = (page.props.auth?.user as Record<string, unknown> | null)?.id;
    if (userId && (window as Record<string, unknown>).Echo) {
        echoChannel = (window as Record<string, unknown>).Echo as typeof window.Echo;
        (echoChannel as typeof window.Echo)
            .private(`App.Models.User.${userId}`)
            .listen('.notification.created', (payload: RecentNotif) => {
                unreadCount.value++;
                notifyFromData(payload.data ?? {});
                recentNotifications.value = [payload, ...recentNotifications.value].slice(0, 8);
            });
    }
});
onUnmounted(() => {
    document.removeEventListener('click', onDocClick, true);
    if (echoChannel && (window as Record<string, unknown>).Echo) {
        const userId = (page.props.auth?.user as Record<string, unknown> | null)?.id;
        if (userId) {
            ((window as Record<string, unknown>).Echo as typeof window.Echo)
                .leave(`App.Models.User.${userId}`);
        }
    }
});

function onDocClick(e: MouseEvent) {
    if (langRef.value && !langRef.value.contains(e.target as Node)) langOpen.value = false;
    if (userRef.value && !userRef.value.contains(e.target as Node)) userOpen.value = false;
    if (notifRef.value && !notifRef.value.contains(e.target as Node)) notifOpen.value = false;
}

function selectLocale(code: string) { langOpen.value = false; switchLocale(code); }
function logout() { userOpen.value = false; router.post(route('logout')); }

const navLinks = [
    { key: 'nav_home',     href: route('home'),          icon: Home     },
    { key: 'nav_servers',  href: route('servers.index'), icon: Server   },
    { key: 'nav_rules',    href: route('rules.index'),   icon: BookOpen },
    { key: 'nav_contacts', href: route('contact.show'),  icon: Phone    },
];

// ── Active navigation ────────────────────────────────────────────
// Without this the header gives no clue which page you're on, which is the
// single biggest reason the nav reads as an undifferentiated row of links.
const currentPath = computed(() => (page.url || '/').split('?')[0].replace(/\/+$/, '') || '/');

function pathOf(href: string): string {
    try {
        // The base only exists so relative hrefs parse; nothing is read from it
        // but the pathname. Using window.location.origin here would make this
        // return the whole URL under SSR, where there is no window — the nav
        // then rendered as inactive on the server and active on the client,
        // which is a hydration mismatch on every page.
        return new URL(href, 'http://localhost').pathname.replace(/\/+$/, '') || '/';
    } catch {
        return href;
    }
}

function isActive(href: string): boolean {
    const target = pathOf(href);

    // Home only matches exactly; every other section also matches its children,
    // so /servers/cs2 still highlights "Servers".
    return target === '/'
        ? currentPath.value === '/'
        : currentPath.value === target || currentPath.value.startsWith(`${target}/`);
}

// Public header links registered by enabled extensions (e.g. Vote).
interface PublicNavItem { label: string; url: string; icon: string }
const publicNavIcons: Record<string, Component> = { ThumbsUp, Trophy, Gift, Package, Server, BookOpen, Star };
const publicNav = computed(() => (page.props.publicNav as PublicNavItem[] | undefined) ?? []);
const userMenu = computed(() => (page.props.userMenu as PublicNavItem[] | undefined) ?? []);
const footerNav = computed(() => (page.props.footerNav as { label: string; url: string }[] | undefined) ?? []);
function publicNavIcon(name: string): Component { return publicNavIcons[name] ?? LinkIcon; }

// ── Footer ───────────────────────────────────────────────────────
interface SiteFooter {
    games: { slug: string; name: string; servers: number; players: number }[];
    servers_online: number;
    servers_total: number;
    players_online: number;
}

const footerData = computed(() => (page.props.siteFooter as SiteFooter | null | undefined) ?? null);

/** Fallback when no footer menu is configured in admin. */
const communityLinks = computed(() => [
    { label: t('home.link_news'), href: route('news.index') },
    { label: t('members.title'), href: route('members.index') },
    { label: t('home.link_rules'), href: route('rules.index') },
    { label: t('home.footer_contact'), href: route('contact.show') },
]);

const accountLinks = computed(() => page.props.auth?.user
    ? [
        ...(page.props.auth.user.username
            ? [{ label: t('home.my_profile'), href: route('profile.show', { username: page.props.auth.user.username }) }]
            : []),
        { label: t('home.my_servers'), href: route('account.favorites') },
        { label: t('navigation.settings'), href: route('account.index') },
    ]
    : [
        { label: t('home.login'), href: route('login') },
        { label: t('home.create_account'), href: route('register') },
        { label: t('home.link_support'), href: route('contact.show') },
    ]);

</script>

<template>
    <div
        class="min-h-screen flex flex-col transition-colors duration-200"
        :class="dark ? 'bg-[#09090b] text-zinc-100' : 'bg-zinc-100 text-zinc-900'"
    >
        <!-- ── Impersonation banner ── -->
        <div
            v-if="page.props.impersonating"
            class="bg-amber-500 text-amber-950 text-sm font-medium px-4 py-2 flex items-center justify-center gap-3 relative z-50"
        >
            <span>{{ t('navigation.impersonating_as', { name: page.props.impersonating.name }) }}</span>
            <button
                type="button"
                class="bg-amber-950/90 text-amber-100 rounded-md px-3 py-1 text-xs font-semibold hover:bg-amber-950 transition-colors"
                @click="router.post(route('impersonation.stop'))"
            >
                {{ t('navigation.stop_impersonating') }}
            </button>
        </div>

        <!--
            First focusable element on the page. Without it a keyboard or
            screen-reader user tabbed through the whole navigation again on
            every single page — WCAG 2.4.1.
        -->
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[200]
                   focus:rounded-xl focus:px-4 focus:py-2.5 focus:text-[13px] focus:font-bold
                   focus:outline-2 focus:outline-offset-2 focus:outline-blue-500"
            :class="dark ? 'focus:bg-zinc-900 focus:text-zinc-100' : 'focus:bg-white focus:text-zinc-900 focus:shadow-lg'"
        >{{ t('navigation.skip_to_content') }}</a>

        <!-- ── Navbar ── -->
        <header
            class="sticky top-0 z-50 border-b backdrop-blur-xl transition-colors duration-200"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]/80' : 'border-zinc-200/70 bg-white/80'"
        >
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-6">

                <!-- Brand — a plain wordmark. Initials in a box read as a
                     placeholder for a logo that isn't there; the name alone is
                     cleaner until the owner can upload a real one. -->
                <Link
                    :href="route('home')"
                    class="group flex items-center shrink-0 rounded-lg px-1 -mx-1 outline-none transition focus-visible:ring-2 focus-visible:ring-blue-500/50"
                >
                    <span
                        class="text-[17px] font-black tracking-tight transition-colors duration-200"
                        :class="dark ? 'text-white group-hover:text-blue-300' : 'text-zinc-900 group-hover:text-blue-600'"
                    >
                        {{ page.props.app.name }}
                    </span>
                </Link>

                <!-- Desktop nav -->
                <nav class="hidden md:flex items-center gap-0.5 flex-1" :aria-label="t('navigation.nav_home')">
                    <a
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        :aria-current="isActive(link.href) ? 'page' : undefined"
                        class="group relative flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="isActive(link.href)
                            ? (dark ? 'text-white bg-zinc-800/70' : 'text-zinc-900 bg-zinc-100')
                            : (dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/40' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100/70')"
                    >
                        <component
                            :is="link.icon" :size="14" :stroke-width="1.75"
                            class="transition-transform duration-200 group-hover:-translate-y-px"
                        />
                        {{ t('navigation.' + link.key) }}
                        <span
                            v-if="isActive(link.href)"
                            class="absolute inset-x-3 -bottom-px h-px rounded-full bg-blue-500"
                            aria-hidden="true"
                        />
                    </a>

                    <!-- Extension-registered public links -->
                    <a
                        v-for="item in publicNav"
                        :key="item.url"
                        :href="item.url"
                        :aria-current="isActive(item.url) ? 'page' : undefined"
                        class="group relative flex items-center gap-1.5 px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="isActive(item.url)
                            ? (dark ? 'text-white bg-zinc-800/70' : 'text-zinc-900 bg-zinc-100')
                            : (dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/40' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100/70')"
                    >
                        <component
                            :is="publicNavIcon(item.icon)" :size="14" :stroke-width="1.75"
                            class="transition-transform duration-200 group-hover:-translate-y-px"
                        />
                        {{ item.label }}
                        <span
                            v-if="isActive(item.url)"
                            class="absolute inset-x-3 -bottom-px h-px rounded-full bg-blue-500"
                            aria-hidden="true"
                        />
                    </a>

                    <template v-for="item in headerMenuItems" :key="item.label">
                        <!-- focus-within keeps the submenu reachable by keyboard; hover
                             alone left it unusable without a mouse. -->
                        <div v-if="item.children?.length" class="relative group">
                            <button
                                type="button"
                                :aria-expanded="false"
                                aria-haspopup="true"
                                class="flex items-center gap-1 px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/40' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100/70'"
                            >
                                {{ item.label }}
                                <ChevronDown
                                    :size="11" :stroke-width="2"
                                    class="transition-transform duration-200 group-hover:rotate-180 group-focus-within:rotate-180"
                                />
                            </button>
                            <div
                                class="absolute top-full left-0 mt-1.5 min-w-[180px] rounded-xl border shadow-xl py-1.5 z-50
                                       opacity-0 invisible -translate-y-1
                                       group-hover:opacity-100 group-hover:visible group-hover:translate-y-0
                                       group-focus-within:opacity-100 group-focus-within:visible group-focus-within:translate-y-0
                                       transition-all duration-200"
                                :class="dark ? 'bg-zinc-900/95 backdrop-blur-xl border-zinc-800 shadow-black/50' : 'bg-white/95 backdrop-blur-xl border-zinc-200 shadow-zinc-300/40'"
                            >
                                <a
                                    v-for="child in item.children"
                                    :key="child.label"
                                    :href="child.url"
                                    :target="child.target"
                                    class="block px-3.5 py-2 text-[13px] outline-none transition-colors focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                                    :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'"
                                >{{ child.label }}</a>
                            </div>
                        </div>
                        <a
                            v-else
                            :href="item.url"
                            :target="item.target"
                            class="px-3 py-2 text-[13px] font-medium rounded-lg outline-none transition-all duration-200 focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/40' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100/70'"
                        >{{ item.label }}</a>
                    </template>
                </nav>

                <!-- Right side -->
                <div class="flex items-center gap-2 ml-auto shrink-0">

                    <!-- Social (desktop) -->
                    <SocialLinks class="hidden lg:flex mr-1" />

                    <!-- Language.
                         The flag is decoration only; the label is the language's
                         own name, because a flag names a country, not a language
                         (Arabic has no flag for that reason). Each option carries
                         lang/hreflang so screen readers pronounce it correctly. -->
                    <div v-if="switcherEnabled && supportedLocales.length > 1" ref="langRef" class="relative">
                        <button
                            type="button"
                            :aria-expanded="langOpen"
                            aria-haspopup="listbox"
                            :aria-label="t('navigation.language')"
                            :title="t('navigation.language')"
                            class="flex items-center gap-2 px-2.5 h-9 text-[12px] rounded-lg border outline-none transition-all duration-200 uppercase tracking-wide font-semibold focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark
                                ? 'border-zinc-800 text-zinc-300 hover:text-white hover:bg-zinc-800/60 hover:border-zinc-700'
                                : 'border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 hover:border-zinc-300'"
                            @click.stop="langOpen = !langOpen"
                            @keydown.escape="langOpen = false"
                        >
                            <FlagIcon :code="currentLocale" />
                            {{ currentLocale }}
                            <ChevronDown
                                :size="11" :stroke-width="2.5"
                                class="transition-transform duration-200"
                                :class="langOpen ? 'rotate-180' : ''"
                            />
                        </button>

                        <transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            leave-active-class="transition duration-100 ease-in"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div
                                v-if="langOpen"
                                role="listbox"
                                :aria-label="t('navigation.language')"
                                class="absolute right-0 top-full mt-1.5 w-52 rounded-xl border shadow-xl py-1.5 z-50"
                                :class="dark ? 'bg-zinc-900/95 backdrop-blur-xl border-zinc-800 shadow-black/50' : 'bg-white/95 backdrop-blur-xl border-zinc-200 shadow-zinc-300/40'"
                                @keydown.escape="langOpen = false"
                            >
                                <button
                                    v-for="locale in supportedLocales"
                                    :key="locale.code"
                                    type="button"
                                    role="option"
                                    :aria-selected="isCurrentLocale(locale.code)"
                                    :lang="locale.code"
                                    class="flex items-center gap-2.5 w-full px-3 py-2 text-[13px] outline-none transition-colors focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                                    :class="isCurrentLocale(locale.code)
                                        ? (dark ? 'text-blue-400 bg-blue-500/10' : 'text-blue-600 bg-blue-50')
                                        : dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'"
                                    @click.stop="selectLocale(locale.code)"
                                >
                                    <FlagIcon :code="locale.code" />
                                    <span class="font-medium">{{ locale.native_name }}</span>
                                    <Check v-if="isCurrentLocale(locale.code)" :size="13" :stroke-width="2.5" class="ml-auto" />
                                </button>
                            </div>
                        </transition>
                    </div>

                    <!-- Theme toggle -->
                    <button
                        type="button"
                        class="w-8 h-8 flex items-center justify-center rounded-md border transition-colors"
                        :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'border-zinc-200 text-zinc-400 hover:text-zinc-500 hover:bg-zinc-100'"
                        :aria-label="dark ? 'Switch to light mode' : 'Switch to dark mode'"
                        @click="toggleTheme"
                    >
                        <Sun v-if="dark" :size="14" :stroke-width="1.75" />
                        <Moon v-else :size="14" :stroke-width="1.75" />
                    </button>

                    <!-- Bell -->
                    <div v-if="page.props.auth?.user" ref="notifRef" class="relative">
                        <button
                            type="button"
                            class="relative w-8 h-8 flex items-center justify-center rounded-md border transition-colors"
                            :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'border-zinc-200 text-zinc-400 hover:text-zinc-500 hover:bg-zinc-100'"
                            aria-label="Notifications"
                            @click.stop="toggleNotifDropdown"
                        >
                            <Bell :size="14" :stroke-width="1.75" />
                            <span v-if="unreadCount > 0"
                                class="absolute -top-1 -right-1 min-w-[16px] h-4 rounded-full bg-red-500 text-white text-[9px] font-bold flex items-center justify-center px-1 leading-none ring-2"
                                :class="dark ? 'ring-zinc-950' : 'ring-white'">
                                {{ unreadCount > 99 ? '99+' : unreadCount }}
                            </span>
                        </button>

                        <div v-if="notifOpen"
                            class="absolute right-0 top-full mt-1 w-80 rounded-lg border shadow-lg z-50 overflow-hidden"
                            :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/40' : 'bg-white border-zinc-200 shadow-zinc-200/60'">
                            <div class="flex items-center justify-between px-3 py-2.5 border-b" :class="dark ? 'border-zinc-800' : 'border-zinc-100'">
                                <span class="text-[12px] font-semibold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">Notifications</span>
                                <button type="button"
                                    class="text-[11px] font-semibold transition-colors disabled:opacity-40"
                                    :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
                                    :disabled="markingAll || unreadCount === 0"
                                    @click.stop="markAllRead"
                                >
                                    {{ markingAll ? 'Marking…' : 'Mark all read' }}
                                </button>
                            </div>

                            <div class="max-h-80 overflow-y-auto">
                                <p v-if="notifLoading" class="px-3 py-6 text-center text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">Loading…</p>
                                <p v-else-if="recentNotifications.length === 0" class="px-3 py-6 text-center text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No notifications yet.</p>
                                <div v-for="n in recentNotifications" :key="n.id"
                                    class="px-3 py-2.5 border-b last:border-0 text-[12px]"
                                    :class="[dark ? 'border-zinc-800/60' : 'border-zinc-100', !n.read ? (dark ? 'bg-blue-500/5' : 'bg-blue-50/50') : '']">
                                    <p :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ notifLabel(n) }}</p>
                                    <p class="text-[11px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ n.created_at }}</p>
                                </div>
                            </div>

                            <Link :href="route('account.index', { tab: 'notifications' })"
                                class="block text-center px-3 py-2 text-[12px] font-semibold border-t transition-colors"
                                :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100' : 'border-zinc-100 text-zinc-500 hover:text-zinc-800'"
                                @click="notifOpen = false">
                                View all
                            </Link>
                        </div>
                    </div>

                    <!-- Auth -->
                    <template v-if="page.props.auth?.user">
                        <div ref="userRef" class="relative">
                            <button
                                type="button"
                                class="flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-md border transition-colors"
                                :class="dark ? 'border-zinc-800 hover:bg-zinc-800/60' : 'border-zinc-200 bg-white hover:bg-zinc-50'"
                                @click.stop="userOpen = !userOpen"
                            >
                                <div class="w-6 h-6 rounded-md overflow-hidden shrink-0 flex items-center justify-center" :class="dark ? 'bg-zinc-800' : 'bg-blue-50'">
                                    <img v-if="page.props.auth.user.avatar" :src="page.props.auth.user.avatar" class="w-full h-full object-cover" />
                                    <span v-else class="text-[10px] font-bold" :class="dark ? 'text-blue-400' : 'text-blue-500'">{{ page.props.auth.user.name.charAt(0) }}</span>
                                </div>
                                <span class="hidden sm:block text-[13px] truncate max-w-[90px]" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ page.props.auth.user.name }}</span>
                                <ChevronDown :size="11" :stroke-width="2.5" class="transition-transform hidden sm:block" :class="[userOpen ? 'rotate-180' : '', dark ? 'text-zinc-500' : 'text-zinc-400']" />
                            </button>

                            <div
                                v-if="userOpen"
                                class="absolute right-0 top-full mt-1.5 w-64 rounded-xl border shadow-xl overflow-hidden z-50"
                                :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/50' : 'bg-white border-zinc-200'"
                            >
                                <!-- Identity header -->
                                <div class="relative px-4 pt-4 pb-3.5 overflow-hidden" :class="dark ? 'bg-zinc-800/40' : 'bg-zinc-50'">
                                    <div class="absolute inset-0 opacity-60" :style="{ background: 'radial-gradient(circle at 15% -10%, rgb(59 130 246 / 0.25), transparent 55%)' }" />
                                    <div class="relative flex items-center gap-3">
                                        <div class="w-11 h-11 rounded-full overflow-hidden shrink-0 flex items-center justify-center ring-2" :class="dark ? 'bg-zinc-800 ring-zinc-900' : 'bg-blue-50 ring-white'">
                                            <img v-if="page.props.auth.user.avatar" :src="page.props.auth.user.avatar" class="w-full h-full object-cover" />
                                            <span v-else class="text-[15px] font-bold" :class="dark ? 'text-blue-400' : 'text-blue-500'">{{ page.props.auth.user.name.charAt(0) }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[13.5px] font-semibold truncate flex items-center gap-1.5" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                                {{ page.props.auth.user.name }}
                                                <BadgeCheck v-if="page.props.auth.user.verified" :size="13" class="text-blue-400 shrink-0" />
                                            </p>
                                            <p v-if="page.props.auth.user.username" class="text-[11px] font-mono truncate" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">@{{ page.props.auth.user.username }}</p>
                                        </div>
                                    </div>
                                    <span v-if="page.props.auth.user.is_admin" class="relative mt-2.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/25">
                                        <ShieldCheck :size="10" :stroke-width="2.5" />
                                        {{ t('home.administrator') }}
                                    </span>
                                </div>

                                <div class="py-1.5">
                                    <Link :href="route('account.index')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <User :size="14" :stroke-width="1.8" />
                                        {{ t('account.my_account') }}
                                    </Link>
                                    <Link v-if="page.props.auth.user.username" :href="route('profile.show', page.props.auth.user.username)" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <UserCircle :size="14" :stroke-width="1.8" />
                                        {{ t('account.view_public_profile') }}
                                    </Link>
                                    <Link :href="route('account.favorites')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <Star :size="14" :stroke-width="1.8" />
                                        {{ t('home.my_servers') }}
                                    </Link>
                                    <Link :href="route('members.index')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <Users :size="14" :stroke-width="1.8" />
                                        {{ t('members.title') }}
                                    </Link>
                                    <!-- Extension-registered user-menu links -->
                                    <a v-for="item in userMenu" :key="item.url" :href="item.url" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <component :is="publicNavIcon(item.icon)" :size="14" :stroke-width="1.8" />
                                        {{ item.label }}
                                    </a>
                                </div>

                                <div v-if="page.props.auth.user.is_admin" class="border-t py-1.5" :class="dark ? 'border-zinc-800' : 'border-zinc-100'">
                                    <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] font-medium transition-colors w-full" :class="dark ? 'text-blue-400 hover:text-blue-300 hover:bg-zinc-800' : 'text-blue-600 hover:text-blue-700 hover:bg-blue-50'" @click="userOpen = false">
                                        <ShieldCheck :size="14" :stroke-width="1.8" />
                                        {{ t('home.admin_panel') }}
                                    </Link>
                                </div>

                                <div class="border-t py-1.5" :class="dark ? 'border-zinc-800' : 'border-zinc-100'">
                                    <button type="button" class="flex items-center gap-2.5 px-4 py-2 text-[13px] w-full text-red-400 hover:text-red-300 transition-colors" :class="dark ? 'hover:bg-zinc-800' : 'hover:bg-red-50'" @click="logout">
                                        <LogOut :size="14" :stroke-width="1.8" />
                                        {{ t('account.sign_out') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-1.5 text-[13px] font-medium rounded-md bg-blue-600 text-white hover:bg-blue-500 transition-colors"
                        >
                            {{ t('home.login') }}
                        </Link>
                    </template>

                    <!-- Mobile hamburger -->
                    <button
                        type="button"
                        class="md:hidden w-8 h-8 flex items-center justify-center rounded-md border transition-colors"
                        :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100' : 'border-zinc-200 text-zinc-500 hover:text-zinc-900'"
                        :aria-label="mobileOpen ? t('navigation.close_menu') : t('navigation.open_menu')"
                        :aria-expanded="mobileOpen"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <component :is="mobileOpen ? X : Menu" :size="15" :stroke-width="1.75" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div
                v-if="mobileOpen"
                class="md:hidden border-t px-4 py-3 flex flex-col gap-0.5"
                :class="dark ? 'border-zinc-800 bg-[#09090b]' : 'border-zinc-200 bg-white'"
            >
                <a
                    v-for="link in navLinks"
                    :key="link.key"
                    :href="link.href"
                    class="flex items-center gap-2.5 px-3 py-2.5 text-[13px] rounded-md transition-colors"
                    :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                    @click="mobileOpen = false"
                >
                    <component :is="link.icon" :size="14" :stroke-width="1.75" />
                    {{ t('navigation.' + link.key) }}
                </a>
                <a
                    v-for="item in publicNav"
                    :key="item.url"
                    :href="item.url"
                    class="flex items-center gap-2.5 px-3 py-2.5 text-[13px] rounded-md transition-colors"
                    :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                    @click="mobileOpen = false"
                >
                    <component :is="publicNavIcon(item.icon)" :size="14" :stroke-width="1.75" />
                    {{ item.label }}
                </a>
                <div class="flex items-center gap-3 pt-3 mt-1 border-t" :class="dark ? 'border-zinc-800' : 'border-zinc-200'">
                    <template v-if="!page.props.auth?.user">
                        <Link :href="route('login')" class="px-4 py-1.5 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-500 transition-colors">{{ t('home.login') }}</Link>
                        <Link :href="route('register')" class="text-sm font-medium transition-colors" :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">{{ t('home.create_account') }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('account.index')" class="flex items-center gap-2 text-sm" :class="dark ? 'text-zinc-400' : 'text-zinc-500'" @click="mobileOpen = false">
                            <User :size="14" :stroke-width="1.8" />
                            {{ t('account.my_account') }}
                        </Link>
                        <button type="button" class="flex items-center gap-2 text-sm text-red-400 ml-auto" @click="logout">
                            <LogOut :size="14" :stroke-width="1.8" />
                            {{ t('account.sign_out') }}
                        </button>
                    </template>
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t transition-colors" :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200/70 bg-zinc-100'">
            <!-- Live strip. The footer is on every public page; most of them
                 have no other indication that anything is running. -->
            <div v-if="footerData" class="border-b" :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
                <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-3 flex items-center gap-x-5 gap-y-1.5 flex-wrap text-[12px]">
                    <span class="inline-flex items-center gap-2 font-bold"
                        :class="dark ? 'text-emerald-400' : 'text-emerald-800'">
                        <span class="hc-live-dot" aria-hidden="true" />
                        {{ t('home.servers_live', { online: footerData.servers_online, total: footerData.servers_total }) }}
                    </span>
                    <span :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        <strong class="font-bold tabular-nums" :class="dark ? 'text-zinc-200' : 'text-zinc-900'">
                            {{ footerData.players_online.toLocaleString() }}
                        </strong>
                        {{ t('home.stat_players').toLowerCase() }}
                    </span>
                    <Link :href="route('servers.index')"
                        class="group ml-auto inline-flex items-center gap-1.5 font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded px-1"
                        :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                        {{ t('home.browse_all') }}
                        <ArrowRight :size="12" :stroke-width="2"
                            class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                    </Link>
                </div>
            </div>

            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]">

                    <!-- Brand -->
                    <div>
                        <Link :href="route('home')"
                            class="inline-block mb-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded">
                            <span class="text-[15px] font-bold tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ page.props.app.name }}</span>
                        </Link>
                        <p class="text-[12.5px] leading-relaxed mb-4 max-w-[280px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('home.footer_tagline') }}
                        </p>
                        <SocialLinks variant="boxed" />
                    </div>

                    <!-- Games actually hosted here — the one thing a player wants
                         from a footer, and it was not linked anywhere. -->
                    <div v-if="footerData?.games.length">
                        <h2 class="text-[11px] uppercase tracking-widest font-bold mb-3.5" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ t('home.footer_games') }}
                        </h2>
                        <ul class="flex flex-col gap-2">
                            <li v-for="g in footerData.games" :key="g.slug">
                                <Link :href="route('servers.game', g.slug)"
                                    class="group flex items-center gap-2 text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'"
                                    :title="t('home.footer_game_hint', { servers: g.servers, players: g.players })">
                                    <span class="truncate">{{ g.name }}</span>
                                    <span class="text-[11px] font-semibold tabular-nums shrink-0"
                                        :class="g.players > 0
                                            ? (dark ? 'text-emerald-400' : 'text-emerald-800')
                                            : (dark ? 'text-zinc-500' : 'text-zinc-500')">{{ g.players }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Community -->
                    <div>
                        <h2 class="text-[11px] uppercase tracking-widest font-bold mb-3.5" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ t('home.footer_community') }}
                        </h2>
                        <ul class="flex flex-col gap-2">
                            <template v-if="footerNavItems.length">
                                <li v-for="item in footerNavItems" :key="item.label">
                                    <a :href="item.url" :target="item.target"
                                        class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                        :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">{{ item.label }}</a>
                                </li>
                            </template>
                            <template v-else>
                                <li v-for="link in communityLinks" :key="link.href">
                                    <Link :href="link.href"
                                        class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                        :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">{{ link.label }}</Link>
                                </li>
                            </template>
                            <!-- Extension-registered footer links -->
                            <li v-for="item in footerNav" :key="item.url">
                                <a :href="item.url"
                                    class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">{{ item.label }}</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Account -->
                    <div>
                        <h2 class="text-[11px] uppercase tracking-widest font-bold mb-3.5" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ t('home.footer_account') }}
                        </h2>
                        <ul class="flex flex-col gap-2">
                            <li v-for="link in accountLinks" :key="link.href">
                                <Link :href="link.href"
                                    class="text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">{{ link.label }}</Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="border-t px-4 sm:px-6 py-4" :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                <div class="max-w-[1600px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                        {{ t('home.footer_copyright').replace(':year', new Date().getFullYear().toString()) }}
                    </p>
                    <nav class="flex items-center flex-wrap gap-4">
                        <!-- Menu-managed legal links take priority -->
                        <template v-if="footerLegalLinks.length">
                            <Link
                                v-for="item in footerLegalLinks"
                                :key="item.label"
                                :href="item.url"
                                :target="item.target"
                                class="text-[12px] transition-colors"
                                :class="dark ? 'text-zinc-500 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-500'"
                            >{{ item.label }}</Link>
                        </template>
                        <!-- Auto-filled from legal_pages table (max 5) -->
                        <template v-else-if="legalPages.length">
                            <Link
                                v-for="p in legalPages"
                                :key="p.slug"
                                :href="`/legal/${p.slug}`"
                                class="text-[12px] transition-colors"
                                :class="dark ? 'text-zinc-500 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-500'"
                            >{{ p.title }}</Link>
                        </template>
                    </nav>
                </div>
            </div>
        </footer>
    </div>

    <ToastManager />
</template>
