<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard, Users, ShieldCheck, Settings, Activity, FileText,
    Puzzle, Paintbrush, Server, UserCircle, LogOut, HeartPulse, Download,
    ScrollText, List, Circle, Package, Globe, Newspaper, BarChart3, DatabaseBackup, BookOpen, Mail, TrendingUp, X,
    KeyRound, Webhook,
} from '@lucide/vue';
import { onMounted, onUnmounted, watch } from 'vue';
import { useAdminSidebar } from '@/composables/useAdminSidebar';

interface NavItem {
    label: string;
    url: string;
    icon: string;
    permission: string | null;
    activePattern: string;
}

interface NavSection {
    heading: string | null;
    items: NavItem[];
}

interface SharedProps {
    adminNav: NavSection[];
    adminBadges: Record<string, number>;
    app: { name: string };
    [key: string]: unknown;
}

const BADGE_MAP: Record<string, string> = {
    '/admin/contact': 'unread_contact',
};

// Curated icon map — extensions should use one of these names in their
// navigation registration. Unknown names fall back to Circle.
const iconMap: Record<string, unknown> = {
    LayoutDashboard, Users, ShieldCheck, Settings, Activity, FileText,
    Puzzle, Paintbrush, Server, HeartPulse, Download, ScrollText, List,
    Package, Globe, Newspaper, BarChart3, DatabaseBackup, BookOpen, Mail, TrendingUp,
    KeyRound, Webhook,
};

const page = usePage<SharedProps>();
const { mobileOpen, close } = useAdminSidebar();

let stopListening: (() => void) | null = null;
let stopOpenWatch: (() => void) | null = null;
let previousBodyOverflow = '';

function handleEscape(event: KeyboardEvent) {
    if (event.key === 'Escape' && mobileOpen.value) {
        close();
    }
}

onMounted(() => {
    stopListening = router.on('navigate', close);
    previousBodyOverflow = document.body.style.overflow;
    stopOpenWatch = watch(mobileOpen, (open) => {
        document.body.style.overflow = open ? 'hidden' : previousBodyOverflow;
    }, { immediate: true });
    window.addEventListener('keydown', handleEscape);
});
onUnmounted(() => {
    stopListening?.();
    stopOpenWatch?.();
    document.body.style.overflow = previousBodyOverflow;
    window.removeEventListener('keydown', handleEscape);
});

function resolveIcon(name: string): unknown {
    return iconMap[name] ?? Circle;
}

function isActive(item: NavItem): boolean {
    const url = page.url;
    if (item.activePattern === '/admin') {
        return url === '/admin' || url === '/admin/';
    }
    return url === item.activePattern
        || url.startsWith(item.activePattern + '/')
        || url.startsWith(item.activePattern + '?');
}

function logout() {
    router.post(route('admin.logout'));
}
</script>

<template>
    <!-- Mobile backdrop -->
    <div
        v-if="mobileOpen"
        class="fixed inset-0 z-40 bg-black/70 backdrop-blur-[2px] lg:hidden"
        aria-hidden="true"
        @click="close"
    />

    <aside
        id="admin-navigation"
        aria-label="Administration navigation"
        class="fixed inset-y-0 left-0 z-50 flex h-dvh w-[min(19rem,88vw)] flex-col border-r border-zinc-800/60 bg-[#0d0d0f]
               shadow-2xl shadow-black/50 transition-transform duration-200 ease-out lg:w-[220px] lg:translate-x-0 lg:shadow-none"
        :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >

        <!-- Logo -->
        <div class="flex items-center gap-3 border-b border-zinc-800/60 px-4 py-4 lg:px-5">
            <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-blue-500/10 border border-blue-500/30 flex items-center justify-center shrink-0">
                    <span class="text-blue-400 text-xs font-bold leading-none">HC</span>
                </div>
                <div class="leading-none">
                    <span class="text-zinc-100 text-sm font-semibold tracking-tight block">{{ page.props.app.name }}</span>
                    <span class="text-zinc-600 text-[10px] mt-0.5 block">Admin Panel</span>
                </div>
            </Link>
            <button
                type="button"
                class="ml-auto grid h-10 w-10 place-items-center rounded-xl text-zinc-500 transition-colors hover:bg-zinc-900 hover:text-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/70 lg:hidden"
                aria-label="Close administration navigation"
                @click="close"
            >
                <X :size="19" aria-hidden="true" />
            </button>
        </div>

        <!-- Navigation (composed from core + extension registrations) -->
        <nav class="flex-1 overflow-y-auto py-3 px-3 flex flex-col gap-4">
            <div v-for="(section, si) in page.props.adminNav" :key="si">
                <p
                    v-if="section.heading"
                    class="text-zinc-700 text-[9px] font-bold uppercase tracking-widest px-3 mb-1.5"
                >
                    {{ section.heading }}
                </p>
                <div class="flex flex-col gap-0.5">
                    <Link
                        v-for="item in section.items"
                        :key="item.url"
                        :href="item.url"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                        :class="isActive(item)
                            ? 'text-blue-400 bg-blue-500/10 border-l-2 border-blue-500'
                            : 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-900/60 border-l-2 border-transparent'"
                    >
                        <component :is="resolveIcon(item.icon)" :size="16" :stroke-width="1.75" class="shrink-0" />
                        <span class="flex-1">{{ item.label }}</span>
                        <span
                            v-if="BADGE_MAP[item.activePattern] && (page.props.adminBadges[BADGE_MAP[item.activePattern]] ?? 0) > 0"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-blue-500 text-white leading-none min-w-[18px] text-center"
                        >
                            {{ page.props.adminBadges[BADGE_MAP[item.activePattern]] }}
                        </span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Footer: profile + logout -->
        <div class="border-t border-zinc-800/60 px-3 py-3 flex flex-col gap-0.5">
            <Link
                :href="route('admin.profile.edit')"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                :class="page.url.startsWith('/admin/profile')
                    ? 'text-blue-400 bg-blue-500/10 border-l-2 border-blue-500'
                    : 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-900/60 border-l-2 border-transparent'"
            >
                <UserCircle :size="16" :stroke-width="1.75" class="shrink-0" />
                Profile
            </Link>
            <button
                type="button"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-zinc-500 hover:text-red-400 hover:bg-red-500/8 transition-colors w-full text-left"
                @click="logout"
            >
                <LogOut :size="16" :stroke-width="1.75" class="shrink-0" />
                Sign out
            </button>
            <p class="text-zinc-600 text-[10px] font-mono px-3 pt-1">v0.9.0-dev</p>
        </div>

    </aside>
</template>
