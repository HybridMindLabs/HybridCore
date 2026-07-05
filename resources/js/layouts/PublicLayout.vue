<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Menu, X, UserCircle, Users, User, LogOut, BadgeCheck,
    Home, Server, BookOpen, Phone, Star, ShieldCheck,
    Globe, ChevronDown, Check, Moon, Sun, Bell,
    ThumbsUp, Trophy, Gift, Package, Link as LinkIcon,
} from '@lucide/vue';
import type { Component } from 'vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { useDesktopNotifications } from '@/composables/useDesktopNotifications';
import ToastManager from '@/components/UI/ToastManager.vue';
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

// Public header links registered by enabled extensions (e.g. Vote).
interface PublicNavItem { label: string; url: string; icon: string }
const publicNavIcons: Record<string, Component> = { ThumbsUp, Trophy, Gift, Package, Server, BookOpen, Star };
const publicNav = computed(() => (page.props.publicNav as PublicNavItem[] | undefined) ?? []);
const userMenu = computed(() => (page.props.userMenu as PublicNavItem[] | undefined) ?? []);
const footerNav = computed(() => (page.props.footerNav as { label: string; url: string }[] | undefined) ?? []);
function publicNavIcon(name: string): Component { return publicNavIcons[name] ?? LinkIcon; }

const socialSvgs: Record<string, { label: string; svg: string; viewBox: string }> = {
    discord: { label: 'Discord', svg: `<path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.033.054a19.9 19.9 0 0 0 5.993 3.03.077.077 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>`, viewBox: '0 0 24 24' },
    steam:   { label: 'Steam',   svg: `<path d="M12 2C6.477 2 2 6.477 2 12c0 4.236 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.181-.78 1.172-4.97 1.172-4.97s-.299-.598-.299-1.482c0-1.388.806-2.428 1.808-2.428.853 0 1.267.641 1.267 1.408 0 .858-.546 2.141-.828 3.329-.236.995.499 1.806 1.476 1.806 1.772 0 3.137-1.868 3.137-4.566 0-2.387-1.716-4.055-4.165-4.055-2.837 0-4.501 2.128-4.501 4.326 0 .857.33 1.776.741 2.279a.3.3 0 0 1 .069.283c-.076.309-.244.995-.277 1.134-.044.183-.146.222-.337.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.967-.527-2.292-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.523 0 10-4.477 10-10S17.523 2 12 2z"/>`, viewBox: '0 0 24 24' },
    twitter: { label: 'X',       svg: `<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.213 5.567 5.951-5.567zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>`, viewBox: '0 0 24 24' },
    youtube: { label: 'YouTube', svg: `<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>`, viewBox: '0 0 24 24' },
};

// Only platforms with a URL configured in admin settings are rendered.
const socialLinks = computed(() => {
    const configured = (page.props.socialLinks as Record<string, string> | undefined) ?? {};
    return Object.entries(configured)
        .filter(([key, url]) => url && socialSvgs[key])
        .map(([key, url]) => ({ ...socialSvgs[key], href: url }));
});
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

        <!-- ── Navbar ── -->
        <header
            class="sticky top-0 z-50 border-b backdrop-blur-sm transition-colors duration-200"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]/95' : 'border-zinc-200/80 bg-zinc-100/95'"
        >
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-14 flex items-center gap-6">

                <!-- Logo -->
                <Link :href="route('home')" class="flex items-center gap-2.5 shrink-0">
                    <span class="text-[15px] font-bold tracking-tight" :class="dark ? 'text-white' : 'text-zinc-900'">
                        {{ page.props.app.name }}
                    </span>
                </Link>

                <!-- Desktop nav -->
                <nav class="hidden md:flex items-center gap-0.5 flex-1">
                    <a
                        v-for="link in navLinks"
                        :key="link.key"
                        :href="link.href"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-[13px] rounded-md transition-colors"
                        :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                    >
                        <component :is="link.icon" :size="14" :stroke-width="1.75" />
                        {{ t('navigation.' + link.key) }}
                    </a>

                    <!-- Extension-registered public links -->
                    <a
                        v-for="item in publicNav"
                        :key="item.url"
                        :href="item.url"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-[13px] rounded-md transition-colors"
                        :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                    >
                        <component :is="publicNavIcon(item.icon)" :size="14" :stroke-width="1.75" />
                        {{ item.label }}
                    </a>

                    <template v-for="item in headerMenuItems" :key="item.label">
                        <div v-if="item.children?.length" class="relative group">
                            <button
                                type="button"
                                class="flex items-center gap-1 px-3 py-1.5 text-[13px] rounded-md transition-colors"
                                :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                            >
                                {{ item.label }}
                                <ChevronDown :size="11" :stroke-width="2" class="transition-transform group-hover:rotate-180" />
                            </button>
                            <div
                                class="absolute top-full left-0 mt-1 min-w-[160px] rounded-lg border shadow-lg py-1 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50"
                                :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/40' : 'bg-white border-zinc-200 shadow-zinc-200/60'"
                            >
                                <a
                                    v-for="child in item.children"
                                    :key="child.label"
                                    :href="child.url"
                                    :target="child.target"
                                    class="block px-3 py-2 text-[13px] transition-colors"
                                    :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'"
                                >{{ child.label }}</a>
                            </div>
                        </div>
                        <a
                            v-else
                            :href="item.url"
                            :target="item.target"
                            class="px-3 py-1.5 text-[13px] rounded-md transition-colors"
                            :class="dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                        >{{ item.label }}</a>
                    </template>
                </nav>

                <!-- Right side -->
                <div class="flex items-center gap-2 ml-auto shrink-0">

                    <!-- Social (desktop) -->
                    <div class="hidden lg:flex items-center gap-3 mr-2">
                        <a
                            v-for="s in socialLinks"
                            :key="s.label"
                            :href="s.href"
                            :aria-label="s.label"
                            target="_blank" rel="noopener noreferrer"
                            class="transition-colors"
                            :class="dark ? 'text-zinc-700 hover:text-zinc-400' : 'text-zinc-300 hover:text-zinc-600'"
                        >
                            <svg :viewBox="s.viewBox" class="w-[14px] h-[14px]" fill="currentColor"><g v-html="s.svg" /></svg>
                        </a>
                    </div>

                    <!-- Language -->
                    <div v-if="switcherEnabled && supportedLocales.length > 1" ref="langRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 text-[12px] rounded-md border transition-colors uppercase tracking-wide font-medium"
                            :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800/60' : 'border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100'"
                            @click.stop="langOpen = !langOpen"
                        >
                            <Globe :size="13" :stroke-width="1.75" />
                            {{ currentLocale }}
                        </button>
                        <div
                            v-if="langOpen"
                            class="absolute right-0 top-full mt-1 w-40 rounded-lg border shadow-lg py-1 z-50"
                            :class="dark ? 'bg-zinc-900 border-zinc-800 shadow-black/40' : 'bg-white border-zinc-200 shadow-zinc-200/60'"
                        >
                            <button
                                v-for="locale in supportedLocales"
                                :key="locale.code"
                                type="button"
                                class="flex items-center justify-between w-full px-3 py-2 text-[12px] transition-colors"
                                :class="isCurrentLocale(locale.code)
                                    ? 'text-blue-500'
                                    : dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'"
                                @click.stop="selectLocale(locale.code)"
                            >
                                <span class="flex items-center gap-2">{{ locale.flag }} {{ locale.native_name }}</span>
                                <Check v-if="isCurrentLocale(locale.code)" :size="11" :stroke-width="2.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Theme toggle -->
                    <button
                        type="button"
                        class="w-8 h-8 flex items-center justify-center rounded-md border transition-colors"
                        :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'border-zinc-200 text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100'"
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
                            :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/60' : 'border-zinc-200 text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100'"
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
                                <span class="text-[12px] font-semibold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">Notifications</span>
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
                                <p v-if="notifLoading" class="px-3 py-6 text-center text-[12px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Loading…</p>
                                <p v-else-if="recentNotifications.length === 0" class="px-3 py-6 text-center text-[12px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">No notifications yet.</p>
                                <div v-for="n in recentNotifications" :key="n.id"
                                    class="px-3 py-2.5 border-b last:border-0 text-[12px]"
                                    :class="[dark ? 'border-zinc-800/60' : 'border-zinc-100', !n.read ? (dark ? 'bg-blue-500/5' : 'bg-blue-50/50') : '']">
                                    <p :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ notifLabel(n) }}</p>
                                    <p class="text-[11px] mt-0.5" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ n.created_at }}</p>
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
                                <span class="hidden sm:block text-[13px] truncate max-w-[90px]" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ page.props.auth.user.name }}</span>
                                <ChevronDown :size="11" :stroke-width="2.5" class="transition-transform hidden sm:block" :class="[userOpen ? 'rotate-180' : '', dark ? 'text-zinc-600' : 'text-zinc-400']" />
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
                                    <Link :href="route('account.index')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <User :size="14" :stroke-width="1.8" />
                                        {{ t('account.my_account') }}
                                    </Link>
                                    <Link v-if="page.props.auth.user.username" :href="route('profile.show', page.props.auth.user.username)" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <UserCircle :size="14" :stroke-width="1.8" />
                                        {{ t('account.view_public_profile') }}
                                    </Link>
                                    <Link :href="route('account.favorites')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <Star :size="14" :stroke-width="1.8" />
                                        {{ t('home.my_servers') }}
                                    </Link>
                                    <Link :href="route('members.index')" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
                                        <Users :size="14" :stroke-width="1.8" />
                                        {{ t('members.title') }}
                                    </Link>
                                    <!-- Extension-registered user-menu links -->
                                    <a v-for="item in userMenu" :key="item.url" :href="item.url" class="flex items-center gap-2.5 px-4 py-2 text-[13px] transition-colors w-full" :class="dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50'" @click="userOpen = false">
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
                        @click="mobileOpen = !mobileOpen"
                    >
                        <component :is="mobileOpen ? X : Menu" :size="15" :stroke-width="1.75" />
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
                        <Link :href="route('register')" class="text-sm font-medium transition-colors" :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-600 hover:text-zinc-900'">{{ t('home.create_account') }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('account.index')" class="flex items-center gap-2 text-sm" :class="dark ? 'text-zinc-400' : 'text-zinc-600'" @click="mobileOpen = false">
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

        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t transition-colors" :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200/70 bg-zinc-100'">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-10">
                <div class="grid grid-cols-1 sm:grid-cols-[2fr_1fr] gap-8">

                    <!-- Brand -->
                    <div>
                        <Link :href="route('home')" class="inline-block mb-3">
                            <span class="text-[15px] font-bold tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ page.props.app.name }}</span>
                        </Link>
                        <p class="text-[13px] leading-relaxed mb-5 max-w-[260px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            {{ t('home.footer_tagline') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <a v-for="s in socialLinks" :key="s.label" :href="s.href" :aria-label="s.label" target="_blank" rel="noopener noreferrer" class="transition-colors" :class="dark ? 'text-zinc-700 hover:text-zinc-400' : 'text-zinc-300 hover:text-zinc-600'">
                                <svg :viewBox="s.viewBox" class="w-[14px] h-[14px]" fill="currentColor"><g v-html="s.svg" /></svg>
                            </a>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div>
                        <p class="text-[11px] uppercase tracking-widest font-semibold mb-4" :class="dark ? 'text-zinc-700' : 'text-zinc-300'">{{ t('home.footer_nav') }}</p>
                        <ul class="flex flex-col gap-2">
                            <template v-if="footerNavItems.length">
                                <li v-for="item in footerNavItems" :key="item.label">
                                    <a :href="item.url" :target="item.target" class="text-[13px] transition-colors" :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-500 hover:text-zinc-900'">{{ item.label }}</a>
                                </li>
                            </template>
                            <template v-else>
                                <li v-for="link in navLinks" :key="link.key">
                                    <a :href="link.href" class="text-[13px] transition-colors" :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-500 hover:text-zinc-900'">{{ t('navigation.' + link.key) }}</a>
                                </li>
                            </template>
                            <!-- Extension-registered footer links -->
                            <li v-for="item in footerNav" :key="item.url">
                                <a :href="item.url" class="text-[13px] transition-colors" :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-500 hover:text-zinc-900'">{{ item.label }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="border-t px-4 sm:px-6 py-4" :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                <div class="max-w-[1600px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-[12px]" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
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
                                :class="dark ? 'text-zinc-700 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-600'"
                            >{{ item.label }}</Link>
                        </template>
                        <!-- Auto-filled from legal_pages table (max 5) -->
                        <template v-else-if="legalPages.length">
                            <Link
                                v-for="p in legalPages"
                                :key="p.slug"
                                :href="`/legal/${p.slug}`"
                                class="text-[12px] transition-colors"
                                :class="dark ? 'text-zinc-700 hover:text-zinc-400' : 'text-zinc-400 hover:text-zinc-600'"
                            >{{ p.title }}</Link>
                        </template>
                    </nav>
                </div>
            </div>
        </footer>
    </div>

    <ToastManager />
</template>
