<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard, Users, ShieldCheck, Settings, Activity, FileText,
    Puzzle, Paintbrush, Server, HeartPulse, Download,
    ScrollText, List, Circle, Package, Globe, Newspaper, BarChart3, DatabaseBackup, BookOpen, Mail, TrendingUp, X,
    KeyRound, Webhook, ChevronDown, CreditCard, Receipt, Flag,
} from '@lucide/vue';
import { nextTick, onMounted, onUnmounted, reactive, watch } from 'vue';
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
    app: { name: string; version: string };
    [key: string]: unknown;
}

const BADGE_MAP: Record<string, string> = {
    '/admin/contact': 'unread_contact',
    '/admin/moderation': 'open_reports',
};

// Curated icon map — extensions should use one of these names in their
// navigation registration. Unknown names fall back to Circle.
const iconMap: Record<string, unknown> = {
    LayoutDashboard, Users, ShieldCheck, Settings, Activity, FileText,
    Puzzle, Paintbrush, Server, HeartPulse, Download, ScrollText, List,
    Package, Globe, Newspaper, BarChart3, DatabaseBackup, BookOpen, Mail, TrendingUp,
    KeyRound, Webhook, CreditCard, Receipt, Flag,
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

// ── Collapsible sections ────────────────────────────────────
// Per-admin, per-browser preference — expanded by default so nothing is
// hidden from someone who hasn't touched it yet.
const COLLAPSE_KEY = 'hc-admin-nav-collapsed';
const collapsed = reactive<Record<string, boolean>>(readCollapsed());

function readCollapsed(): Record<string, boolean> {
    try {
        return JSON.parse(localStorage.getItem(COLLAPSE_KEY) ?? '{}');
    } catch {
        return {};
    }
}

function toggleSection(heading: string) {
    collapsed[heading] = !collapsed[heading];
    localStorage.setItem(COLLAPSE_KEY, JSON.stringify(collapsed));
}

function sectionHasActiveItem(section: NavSection): boolean {
    return section.items.some(isActive);
}

// A section stays visually expanded while it contains the current page,
// regardless of the saved preference — collapsing your own location out
// from under you would defeat the point of the auto-scroll below.
function sectionExpanded(section: NavSection): boolean {
    return !collapsed[section.heading ?? ''] || sectionHasActiveItem(section);
}

onMounted(() => {
    stopListening = router.on('navigate', close);
    previousBodyOverflow = document.body.style.overflow;
    stopOpenWatch = watch(mobileOpen, (open) => {
        document.body.style.overflow = open ? 'hidden' : previousBodyOverflow;
    }, { immediate: true });
    window.addEventListener('keydown', handleEscape);

    // The whole sidebar remounts on every navigation (no persistent Inertia
    // layout), which used to leave a long nav list scrolled back to the top
    // after every click — this restores "where you are" every time instead
    // of relying on scroll state that never survives the remount.
    nextTick(() => {
        document.querySelector('#admin-navigation a[aria-current="page"]')?.scrollIntoView({ block: 'nearest' });
    });
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
        class="fixed inset-y-0 left-0 z-50 flex h-dvh w-[min(19rem,88vw)] flex-col border-r border-zinc-800/60 bg-admin-sunken
               shadow-2xl shadow-black/50 transition-transform duration-200 ease-out lg:w-64 lg:translate-x-0 lg:shadow-none"
        :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >

        <!-- Logo -->
        <div class="flex items-center gap-3 border-b border-zinc-800/60 px-4 py-4 lg:px-5">
            <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/30 flex items-center justify-center shrink-0">
                    <span class="text-blue-400 text-xs font-bold leading-none">HC</span>
                </div>
                <div class="leading-none">
                    <span class="text-zinc-100 text-sm font-semibold tracking-tight block">{{ page.props.app.name }}</span>
                    <span class="text-zinc-500 text-[11px] mt-0.5 block">Admin Panel</span>
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
        <nav class="admin-scroll flex-1 overflow-y-auto py-3 px-3 flex flex-col gap-3">
            <div v-for="(section, si) in page.props.adminNav" :key="si">
                <button
                    v-if="section.heading"
                    type="button"
                    class="flex w-full items-center gap-1 px-3 mb-1.5 text-zinc-500 text-[11px] font-semibold uppercase tracking-wider hover:text-zinc-300 transition-colors"
                    @click="toggleSection(section.heading)"
                >
                    <span class="flex-1 text-left">{{ section.heading }}</span>
                    <ChevronDown :size="12" :stroke-width="2.25" class="transition-transform" :class="sectionExpanded(section) ? '' : '-rotate-90'" />
                </button>
                <div v-show="sectionExpanded(section)" class="flex flex-col gap-0.5">
                    <Link
                        v-for="item in section.items"
                        :key="item.url"
                        :href="item.url"
                        :aria-current="isActive(item) ? 'page' : undefined"
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

        <div class="border-t border-zinc-800/60 px-5 py-3">
            <Link
                :href="route('admin.updates.index')"
                title="Updates and changelog"
                class="text-zinc-600 hover:text-zinc-300 text-[11px] font-mono transition-colors"
            >v{{ page.props.app.version }}</Link>
        </div>

    </aside>
</template>
