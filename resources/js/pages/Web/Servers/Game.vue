<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Check, Search, RefreshCw, LayoutGrid, List, Wifi, Lock, Shield, X, ArrowUp, ArrowDown, Play, Star } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import PlayerAreaChart from '@/components/UI/PlayerAreaChart.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref, reactive, onUnmounted } from 'vue';

interface ServerStatus {
    is_online: boolean; map: string | null; players_online: number;
    players_max: number; ping: number | null;
    is_password_protected: boolean; vac_secured: boolean;
}
interface GameServer {
    id: number; ip: string; port: number; address: string; name: string;
    country_code: string | null; row_image: string | null;
    tags: string[]; is_favourited: boolean;
    status: ServerStatus | null;
}
interface Game {
    id: number; name: string; slug: string; icon: string;
    color: string; cover_url: string | null;
}
interface Stats { total: number; online: number; players: number; }

interface InsightRange {
    history: { t: string; players: number | null }[];
    peak: number;
    average: number;
    uptime: number;
    samples: number;
}
interface Insights {
    updated_at: string;
    ranges: Record<'24h' | '7d' | '30d', InsightRange>;
    maps: { map: string; share: number }[];
}

const props = defineProps<{ game: Game; servers: GameServer[]; stats: Stats; insights: Insights }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const search = ref('');
const activeFilter = ref<'all' | 'online' | 'offline' | 'empty' | 'hasplayers' | 'full'>('all');
const viewMode = ref<'table' | 'grid'>('table');
const activeTab = ref<'all' | 'favourites'>('all');
const sortCol = ref<'name' | 'players' | 'map' | 'ping'>('players');
const sortDir = ref<'asc' | 'desc'>('desc');
const copiedId = ref<number | null>(null);
const favouriteLoading = ref<number | null>(null);

const counts = computed(() => ({
    all: props.servers.length,
    online: props.servers.filter(s => s.status?.is_online).length,
    offline: props.servers.filter(s => !s.status?.is_online).length,
    empty: props.servers.filter(s => s.status?.is_online && s.status.players_online === 0).length,
    hasplayers: props.servers.filter(s => s.status?.is_online && s.status.players_online > 0).length,
    full: props.servers.filter(s => s.status?.is_online && s.status.players_online >= s.status.players_max && s.status.players_max > 0).length,
}));

const filtered = computed(() => {
    let list = [...props.servers];
    if (activeTab.value === 'favourites') list = list.filter(s => s.is_favourited);
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(s => s.name.toLowerCase().includes(q) || s.address.includes(q) || (s.status?.map ?? '').toLowerCase().includes(q));
    }
    if (activeFilter.value === 'online')     list = list.filter(s => s.status?.is_online);
    else if (activeFilter.value === 'offline')    list = list.filter(s => !s.status?.is_online);
    else if (activeFilter.value === 'empty')      list = list.filter(s => s.status?.is_online && s.status.players_online === 0);
    else if (activeFilter.value === 'hasplayers') list = list.filter(s => s.status?.is_online && (s.status.players_online ?? 0) > 0);
    else if (activeFilter.value === 'full')       list = list.filter(s => s.status?.is_online && s.status.players_online >= s.status.players_max && s.status.players_max > 0);

    list.sort((a, b) => {
        let va: number | string = 0, vb: number | string = 0;
        if (sortCol.value === 'players') { va = a.status?.players_online ?? -1; vb = b.status?.players_online ?? -1; }
        else if (sortCol.value === 'ping') { va = a.status?.ping ?? 9999; vb = b.status?.ping ?? 9999; }
        else if (sortCol.value === 'map')  { va = a.status?.map ?? ''; vb = b.status?.map ?? ''; }
        else { va = a.name; vb = b.name; }
        if (va < vb) return sortDir.value === 'asc' ? -1 : 1;
        if (va > vb) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
    return list;
});

function toggleSort(col: typeof sortCol.value) {
    if (sortCol.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else { sortCol.value = col; sortDir.value = 'desc'; }
}

async function copyIp(server: GameServer) {
    try {
        await navigator.clipboard.writeText(server.address);
    } catch {
        // The Clipboard API needs a secure context; over plain http this throws
        // and the copy silently did nothing before.
        const field = document.createElement('textarea');
        field.value = server.address;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        try {
            document.execCommand('copy');
        } finally {
            document.body.removeChild(field);
        }
    }

    copiedId.value = server.id;
    if (copyTimer) clearTimeout(copyTimer);
    copyTimer = setTimeout(() => { copiedId.value = null; }, 1600);
}

async function toggleFavourite(server: GameServer) {
    const previous = server.is_favourited;
    server.is_favourited = !previous; // optimistic — flips instantly, rolled back on failure
    favouriteLoading.value = server.id;
    try {
        const res = await fetch(route('servers.favourite', server.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        });
        if (!res.ok) throw new Error('request failed');
        const data = await res.json();
        server.is_favourited = data.favourited;
    } catch {
        server.is_favourited = previous;
    } finally { favouriteLoading.value = null; }
}

const refreshing = ref(false);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

function refresh() {
    router.reload({
        only: ['servers', 'stats'],
        onStart: () => { refreshing.value = true; },
        onFinish: () => { refreshing.value = false; },
    });
}

onUnmounted(() => { if (copyTimer) clearTimeout(copyTimer); });

function playerBarWidth(s: ServerStatus | null) {
    if (!s || s.players_max === 0) return '0%';
    return `${Math.min(100, Math.round((s.players_online / s.players_max) * 100))}%`;
}
function playerBarColor(s: ServerStatus | null) {
    if (!s || s.players_max === 0) return '#52525b';
    const pct = s.players_online / s.players_max;
    if (pct >= 0.9) return '#ef4444';
    if (pct >= 0.6) return '#f59e0b';
    return '#22c55e';
}
/** Tailwind classes rather than a raw hex, so ping keeps its contrast in both themes. */
function pingClass(s: ServerStatus | null): string {
    if (s?.ping == null) return dark.value ? 'text-zinc-600' : 'text-zinc-500';
    if (s.ping < 50) return dark.value ? 'text-emerald-400' : 'text-emerald-700';
    if (s.ping < 100) return dark.value ? 'text-amber-400' : 'text-amber-700';
    return dark.value ? 'text-red-400' : 'text-red-600';
}

const failedRowImages = reactive(new Set<number>());

const serverTabs = computed<{ key: 'all' | 'favourites'; label: string; hint: string; count: number }[]>(() => [
    {
        key: 'all',
        label: t('servers.filter_all'),
        hint: t('servers.tab_all_hint'),
        count: props.servers.length,
    },
    {
        key: 'favourites',
        label: t('servers.favourite'),
        hint: t('servers.tab_favourites_hint'),
        count: props.servers.filter(s => s.is_favourited).length,
    },
]);

const heroStats = computed(() => [
    {
        value: props.stats.total,
        label: t('servers.servers_label'),
        hint: t('servers.hero_total_hint'),
        accent: false,
    },
    {
        value: props.stats.online,
        label: t('servers.online_now'),
        hint: t('servers.hero_online_hint'),
        accent: props.stats.online > 0,
    },
    {
        value: props.stats.players,
        label: t('servers.players_label'),
        hint: t('servers.hero_players_hint'),
        accent: false,
    },
]);

// ── Statistics ───────────────────────────────────────────────────
type StatTab = '24h' | '7d' | '30d' | 'maps';

const activeStatTab = ref<StatTab>('24h');

const statTabs = computed<{ key: StatTab; label: string }[]>(() => [
    { key: '24h', label: t('servers.range_24h') },
    { key: '7d', label: t('servers.range_7d') },
    { key: '30d', label: t('servers.range_30d') },
    { key: 'maps', label: t('servers.range_maps') },
]);

const activeRange = computed(() => {
    const key = activeStatTab.value === 'maps' ? '24h' : activeStatTab.value;
    return props.insights.ranges[key];
});

const updatedLabel = computed(() =>
    new Date(props.insights.updated_at).toLocaleString([], {
        dateStyle: 'short',
        timeStyle: 'medium',
    }),
);

const activityTiles = computed(() => [
    {
        label: t('servers.average_players'),
        value: activeRange.value.average.toLocaleString(),
        suffix: '',
        hint: t('servers.average_players_hint'),
    },
    {
        label: t('servers.peak_players'),
        value: activeRange.value.peak.toLocaleString(),
        suffix: '',
        hint: t('servers.peak_players_hint'),
    },
    {
        label: t('servers.uptime_label'),
        value: String(activeRange.value.uptime),
        suffix: '%',
        hint: t('servers.uptime_label_hint'),
    },
]);
</script>

<template>
    <Head :title="t('servers.game_servers_title', { game: game.name })" />

    <PublicLayout>

        <!-- ── Hero banner ── -->
        <section class="relative overflow-hidden" style="min-height: 210px"
            :aria-label="t('servers.game_servers_title', { game: game.name })">
            <div v-if="game.cover_url" class="absolute inset-0 overflow-hidden" aria-hidden="true">
                <div
                    class="absolute inset-[-10%] bg-cover bg-center"
                    :style="{
                        backgroundImage: `url(${game.cover_url})`,
                        filter: dark
                            ? 'blur(16px) brightness(0.42) saturate(1.2)'
                            : 'blur(16px) brightness(1.06) saturate(1.1)',
                    }"
                />
            </div>
            <div v-else class="absolute inset-0" aria-hidden="true"
                :style="{ background: `linear-gradient(135deg, ${game.color}40, ${game.color}10)` }" />

            <!-- The side vignette was hard-coded to rgba(9,9,11,0.65) in both
                 themes, so in light mode the near-black heading sat on a dark
                 wash. Each theme now scrims with its own page colour, strong
                 under the text on the left and fading out to the right. -->
            <div class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to right, rgba(9,9,11,0.88) 0%, rgba(9,9,11,0.70) 45%, transparent 85%)'
                    : 'background: linear-gradient(to right, rgba(236,238,242,0.95) 0%, rgba(236,238,242,0.82) 45%, rgba(236,238,242,0.30) 85%)'" />
            <div class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to bottom, transparent 40%, rgba(9,9,11,1) 100%)'
                    : 'background: linear-gradient(to bottom, transparent 35%, rgba(236,238,242,1) 100%)'" />

            <div class="absolute inset-0 pointer-events-none" aria-hidden="true"
                :class="dark ? 'opacity-50' : 'opacity-[0.3]'"
                :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 pb-0">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    { label: t('servers.title'), href: route('servers.index') },
                    { label: game.name },
                ]" />

                <div class="grid gap-6 lg:gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)] lg:items-end mt-4 mb-5">

                    <div class="max-w-xl">
                        <div class="hc-hero-in inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[10.5px] font-bold uppercase tracking-widest"
                            :class="stats.online > 0
                                ? (dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700')
                                : (dark ? 'border-zinc-700 bg-zinc-800/60 text-zinc-400' : 'border-zinc-300 bg-zinc-200/70 text-zinc-600')"
                            aria-live="polite">
                            <span v-if="stats.online > 0" class="hc-live-dot" aria-hidden="true" />
                            {{ t('servers.game_live_badge', { online: stats.online, total: stats.total }) }}
                        </div>

                        <div class="hc-hero-in hc-hero-in--1 flex items-center gap-4 mt-4">
                            <div class="w-14 h-14 rounded-xl shrink-0 shadow-lg ring-2 overflow-hidden"
                                :class="dark ? 'ring-zinc-800' : 'ring-white'">
                                <GameIcon :slug="game.slug" :alt="game.name" />
                            </div>
                            <h1 class="text-[26px] sm:text-[34px] font-black leading-tight tracking-tight"
                                :class="dark ? 'text-white' : 'text-zinc-900'">
                                {{ t('servers.game_servers_title', { game: game.name }) }}
                            </h1>
                        </div>

                        <p class="hc-hero-in hc-hero-in--2 mt-3 text-[14px] sm:text-[15px] leading-relaxed max-w-lg"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                            {{ t('servers.game_hero_description', { game: game.name }) }}
                        </p>
                    </div>

                    <!-- Every figure carries an explanation, so nobody has to
                         guess what "online" is counting. -->
                    <dl class="hc-hero-in hc-hero-in--2 grid grid-cols-3 gap-2.5">
                        <div v-for="(item, i) in heroStats" :key="item.label"
                            class="hc-reveal rounded-xl border px-3 py-2.5 backdrop-blur-md"
                            :style="{ animationDelay: 0.18 + i * 0.06 + 's' }"
                            :class="dark
                                ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                                : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'"
                            :title="item.hint">
                            <dd class="text-[20px] font-black leading-none tabular-nums"
                                :class="item.accent
                                    ? (dark ? 'text-emerald-400' : 'text-emerald-700')
                                    : (dark ? 'text-zinc-100' : 'text-zinc-900')">
                                {{ item.value.toLocaleString() }}
                            </dd>
                            <dt class="text-[10px] font-bold uppercase tracking-widest mt-1.5"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.label }}</dt>
                            <p class="text-[10.5px] leading-snug mt-1"
                               :class="dark ? 'text-zinc-600' : 'text-zinc-500'">{{ item.hint }}</p>
                        </div>
                    </dl>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-0.5 border-b" :class="dark ? 'border-white/10' : 'border-zinc-300'">
                    <button
                        v-for="tab in serverTabs"
                        :key="tab.key"
                        type="button"
                        class="flex items-center gap-2 px-5 py-3 text-[13px] font-semibold border-b-2 -mb-px transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                        :class="activeTab === tab.key
                            ? dark ? 'text-white border-white' : 'text-zinc-900 border-zinc-900'
                            : dark ? 'text-white/45 border-transparent hover:text-white/75' : 'text-zinc-600 border-transparent hover:text-zinc-900'"
                        :aria-pressed="activeTab === tab.key"
                        :title="tab.hint"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold tabular-nums"
                            :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-600'">{{ tab.count }}</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- ── Content ── -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-5">



            <!-- Filter chips -->
            <p class="text-[12.5px] mb-2.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ t('servers.filter_hint') }}
            </p>
            <div class="flex items-center gap-1.5 mb-4 flex-wrap" role="group" :aria-label="t('servers.filter_group')">
                <button
                    v-for="[key, label] in [
                        ['all', t('servers.filter_all')], ['online', t('servers.online')],
                        ['offline', t('servers.offline')], ['empty', t('servers.filter_empty')],
                        ['hasplayers', t('servers.filter_has_players')], ['full', t('servers.filter_full')],
                    ]"
                    :key="key"
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="activeFilter === key
                        ? dark ? 'bg-blue-600 border-blue-600 text-white' : 'bg-blue-600 border-blue-600 text-white'
                        : dark
                            ? 'border-zinc-800/80 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 hover:bg-white/[0.04]'
                            : 'border-zinc-300 bg-white text-zinc-600 hover:text-zinc-900 hover:border-zinc-400'"
                    :aria-pressed="activeFilter === key"
                    @click="activeFilter = key as any"
                >
                    {{ label }}
                    <span
                        class="px-1.5 py-0.5 rounded-md text-[10px] font-bold tabular-nums"
                        :class="activeFilter === key
                            ? 'bg-black/25 text-white'
                            : dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-600'"
                    >{{ counts[key as keyof typeof counts] }}</span>
                </button>
            </div>

            <!-- Toolbar -->
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <div class="flex-1 min-w-[200px] max-w-sm">
                    <label for="server-search" class="sr-only">{{ t('servers.search') }}</label>
                    <div class="relative">
                        <Search :size="14" :stroke-width="1.8" aria-hidden="true"
                            class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'" />
                        <input
                            id="server-search"
                            v-model="search"
                            type="search"
                            autocomplete="off"
                            :placeholder="t('servers.search')"
                            class="w-full pl-9 pr-9 py-2.5 rounded-lg border text-[13px] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                            :class="dark
                                ? 'border-zinc-800/80 bg-[#111113] text-zinc-100 placeholder:text-zinc-600 focus:border-zinc-700'
                                : 'border-zinc-300 bg-white text-zinc-900 placeholder:text-zinc-500 focus:border-blue-500/60'"
                        />
                        <button v-if="search" type="button" @click="search = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center rounded transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-500 hover:text-zinc-900'"
                            :aria-label="t('servers.clear_search')" :title="t('servers.clear_search')">
                            <X :size="14" :stroke-width="2" aria-hidden="true" />
                        </button>
                    </div>
                    <p class="sr-only" role="status" aria-live="polite">
                        {{ t('servers.results_servers', { count: filtered.length }) }}
                    </p>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <!-- View toggle -->
                    <div class="flex items-center rounded-lg border overflow-hidden" role="group"
                        :aria-label="t('servers.view_group')"
                        :class="dark ? 'border-zinc-800/80' : 'border-zinc-300'">
                        <button
                            v-for="[mode, icon, label] in [['table', List, t('servers.view_table')], ['grid', LayoutGrid, t('servers.view_grid')]]"
                            :key="mode"
                            type="button"
                            class="px-3 py-2.5 flex items-center gap-1.5 text-[12px] font-semibold transition border-r last:border-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                            :class="[
                                viewMode === mode
                                    ? dark ? 'bg-zinc-800 text-zinc-100' : 'bg-zinc-200 text-zinc-900'
                                    : dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-600 hover:text-zinc-900',
                                dark ? 'border-zinc-800/80' : 'border-zinc-300',
                            ]"
                            :aria-pressed="viewMode === mode"
                            @click="viewMode = mode as any"
                        >
                            <component :is="icon" :size="13" :stroke-width="1.8" aria-hidden="true" />
                            {{ label }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="flex items-center gap-1.5 px-3 py-2.5 rounded-lg border text-[12px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 disabled:opacity-60"
                        :class="dark
                            ? 'border-zinc-800/80 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700'
                            : 'border-zinc-300 text-zinc-600 hover:text-zinc-900 hover:border-zinc-400'"
                        :disabled="refreshing"
                        @click="refresh"
                    >
                        <RefreshCw :size="13" :stroke-width="1.8" aria-hidden="true"
                            :class="refreshing ? 'animate-spin' : ''" />
                        {{ t('servers.refresh') }}
                    </button>
                </div>
            </div>

            <!-- ── Table view ── -->
            <template v-if="viewMode === 'table'">
                <div
                    class="rounded-xl border overflow-hidden"
                    :class="dark
                        ? 'border-zinc-800/70 bg-[#111113]'
                        : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
                >
                    <!-- Header -->
                    <div
                        class="hidden lg:grid grid-cols-[minmax(0,1fr)_170px_110px_150px_80px_96px] border-b text-[11px] font-semibold uppercase tracking-wider"
                        :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-600' : 'border-zinc-100 bg-zinc-50 text-zinc-400'"
                    >
                        <button
                            class="text-left px-5 py-3 flex items-center gap-1 hover:text-current transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50 transition"
                            :class="sortCol === 'name' ? (dark ? 'text-zinc-200' : 'text-zinc-900') : ''"
                            :aria-label="t('servers.sort_by', { column: t('servers.col_name') })"
                            @click="toggleSort('name')">
                            {{ t('servers.col_name') }}
                            <component v-if="sortCol === 'name'" :is="sortDir === 'asc' ? ArrowUp : ArrowDown"
                                :size="11" :stroke-width="2.5" aria-hidden="true" />
                        </button>
                        <div class="px-4 py-3">{{ t('servers.col_connection') }}</div>
                        <button
                            class="px-4 py-3 text-right flex items-center justify-end gap-1 hover:text-current transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50 transition"
                            :class="sortCol === 'players' ? (dark ? 'text-zinc-200' : 'text-zinc-900') : ''"
                            :aria-label="t('servers.sort_by', { column: t('servers.col_online') })"
                            @click="toggleSort('players')">
                            {{ t('servers.col_online') }}
                            <component v-if="sortCol === 'players'" :is="sortDir === 'asc' ? ArrowUp : ArrowDown"
                                :size="11" :stroke-width="2.5" aria-hidden="true" />
                        </button>
                        <button
                            class="px-4 py-3 flex items-center gap-1 hover:text-current transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50 transition"
                            :class="sortCol === 'map' ? (dark ? 'text-zinc-200' : 'text-zinc-900') : ''"
                            :aria-label="t('servers.sort_by', { column: t('servers.col_map') })"
                            @click="toggleSort('map')">
                            {{ t('servers.col_map') }}
                            <component v-if="sortCol === 'map'" :is="sortDir === 'asc' ? ArrowUp : ArrowDown"
                                :size="11" :stroke-width="2.5" aria-hidden="true" />
                        </button>
                        <button
                            class="px-4 py-3 text-right flex items-center justify-end gap-1 hover:text-current transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50 transition"
                            :class="sortCol === 'ping' ? (dark ? 'text-zinc-200' : 'text-zinc-900') : ''"
                            :aria-label="t('servers.sort_by', { column: t('servers.col_ping') })"
                            @click="toggleSort('ping')">
                            {{ t('servers.col_ping') }}
                            <component v-if="sortCol === 'ping'" :is="sortDir === 'asc' ? ArrowUp : ArrowDown"
                                :size="11" :stroke-width="2.5" aria-hidden="true" />
                        </button>
                        <div class="px-4 py-3" />
                    </div>

                    <!-- Rows -->
                    <div
                        v-for="server in filtered"
                        :key="server.id"
                        class="hc-server-row group relative grid grid-cols-[minmax(0,1fr)_auto] lg:grid-cols-[minmax(0,1fr)_170px_110px_150px_80px_110px] items-center border-b last:border-0 overflow-hidden transition-colors"
                        :class="dark
                            ? 'border-zinc-800/50 hover:bg-white/[0.03]'
                            : 'border-zinc-100 hover:bg-zinc-100/70'"
                    >
                        <!-- Current map bleeding in from the right, masked away
                             before it reaches the text. -->
                        <div v-if="server.row_image && !failedRowImages.has(server.id)"
                            class="absolute inset-y-0 right-0 w-[45%] pointer-events-none hidden sm:block"
                            aria-hidden="true">
                            <img :src="server.row_image" alt="" loading="lazy" decoding="async"
                                class="hc-server-map w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                :class="dark ? 'opacity-[0.4] group-hover:opacity-[0.55]' : 'opacity-[0.2] group-hover:opacity-[0.3]'"
                                @error="failedRowImages.add(server.id)" />
                        </div>

                        <span class="absolute left-0 inset-y-0 w-[3px]" aria-hidden="true"
                            :style="{ background: game.color }"
                            :class="server.status?.is_online ? 'opacity-100' : 'opacity-30'" />

                        <!-- Name -->
                        <div class="relative px-5 py-3 flex items-center gap-3 min-w-0">
                            <div class="relative shrink-0">
                                <span class="block w-9 h-9 rounded-lg overflow-hidden">
                                    <GameIcon :slug="game.slug" :alt="game.name" />
                                </span>
                                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                                    :class="[
                                        server.status?.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-600' : 'bg-zinc-400',
                                        dark ? 'border-[#111113]' : 'border-white',
                                    ]"
                                    role="img"
                                    :aria-label="server.status?.is_online ? t('home.status_online') : t('home.status_offline')"
                                    :title="server.status?.is_online ? t('home.status_online') : t('home.status_offline')" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <Link :href="route('servers.show', { game: game.slug, ip: server.ip, port: server.port })"
                                    class="block text-[13.5px] font-bold truncate transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                    :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                                    {{ server.name }}
                                </Link>
                                <div class="flex items-center gap-1.5 mt-0.5 min-w-0">
                                    <Lock v-if="server.status?.is_password_protected" :size="10" :stroke-width="2"
                                        class="text-amber-500 shrink-0" :title="t('servers.password_protected')" />
                                    <Shield v-if="server.status?.vac_secured" :size="10" :stroke-width="2"
                                        class="text-emerald-500 shrink-0" :title="t('servers.vac_secured')" />
                                    <span class="text-[11.5px] font-mono truncate"
                                        :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                        {{ server.status?.map ?? '—' }}
                                    </span>
                                    <span v-if="server.country_code"
                                        class="shrink-0 px-1.5 py-0.5 rounded text-[9px] font-bold tracking-wide"
                                        :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-600'"
                                        :title="server.country_code.toUpperCase()">{{ server.country_code.toUpperCase() }}</span>
                                    <!-- Below lg the dedicated columns are gone, so the
                                         two numbers a player decides on move here. -->
                                    <span class="lg:hidden text-[11px] font-mono shrink-0"
                                        :class="pingClass(server.status)">· {{ server.status?.ping ?? '—' }}ms</span>
                                </div>
                            </div>
                        </div>

                        <!-- Address + copy -->
                        <div class="relative hidden lg:flex px-4 items-center gap-1.5 min-w-0">
                            <span class="text-[12px] font-mono truncate flex-1"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ server.address }}</span>
                            <button
                                type="button"
                                class="w-7 h-7 flex items-center justify-center rounded-md transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="copiedId === server.id
                                    ? 'text-emerald-500'
                                    : dark ? 'text-zinc-600 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/70'"
                                :aria-label="t('home.copy_address', { address: server.address })"
                                :title="copiedId === server.id ? t('home.copied') : t('home.copy_address', { address: server.address })"
                                @click="copyIp(server)"
                            >
                                <component :is="copiedId === server.id ? Check : Copy" :size="13" :stroke-width="2" aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Ping -->
                        <div class="relative hidden lg:flex px-4 items-center justify-end">
                            <span v-if="server.status?.ping != null"
                                class="text-[12.5px] font-mono tabular-nums" :class="pingClass(server.status)">
                                {{ server.status.ping }}ms
                            </span>
                            <span v-else class="text-[12px]" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">—</span>
                        </div>

                        <!-- Tags -->
                        <div class="relative hidden lg:flex px-4 items-center gap-1 min-w-0">
                            <span
                                v-for="tag in server.tags.slice(0, 2)"
                                :key="tag"
                                class="text-[10px] font-semibold px-1.5 py-0.5 rounded truncate"
                                :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-600'"
                            >{{ tag }}</span>
                        </div>

                        <!-- Connect -->
                        <div class="relative hidden lg:flex items-center justify-end px-2">
                            <a
                                :href="route('servers.connect', { game: game.slug, ip: server.ip, port: server.port })"
                                class="w-8 h-8 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-700 hover:bg-emerald-500/10'"
                                :aria-label="t('home.connect_to', { name: server.name })"
                                :title="t('home.connect_to', { name: server.name })"
                            >
                                <Play :size="15" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                            </a>
                        </div>

                        <!-- Players + favourite -->
                        <div class="relative flex items-center justify-end gap-1.5 px-4 lg:px-3 py-3">
                            <span v-if="server.status?.is_online"
                                class="px-2.5 py-1 rounded-md text-[12px] font-bold tabular-nums text-white min-w-[62px] text-center"
                                :style="{ backgroundColor: playerBarColor(server.status) }"
                                :title="t('servers.players_of_max', {
                                    players: server.status.players_online,
                                    max: server.status.players_max,
                                })">
                                {{ server.status.players_online }}/{{ server.status.players_max }}
                            </span>
                            <span v-else
                                class="px-2.5 py-1 rounded-md text-[11px] font-bold min-w-[62px] text-center"
                                :class="dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-200 text-zinc-600'">
                                {{ t('servers.offline') }}
                            </span>

                            <button
                                type="button"
                                class="w-8 h-8 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 disabled:opacity-50"
                                :class="server.is_favourited
                                    ? 'text-amber-400'
                                    : dark ? 'text-zinc-600 hover:text-amber-400 hover:bg-white/[0.06]' : 'text-zinc-400 hover:text-amber-500 hover:bg-zinc-200/70'"
                                :aria-pressed="server.is_favourited"
                                :aria-label="t(server.is_favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
                                :title="t(server.is_favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
                                :disabled="favouriteLoading === server.id"
                                @click="toggleFavourite(server)"
                            >
                                <Star :size="15" :stroke-width="1.9" :fill="server.is_favourited ? 'currentColor' : 'none'" aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <div v-if="!filtered.length" class="flex flex-col items-center text-center px-6 py-16">
                        <span class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                            :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                            <Wifi :size="22" :stroke-width="1.5" />
                        </span>
                        <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                            {{ t('servers.no_servers') }}
                        </p>
                        <p class="text-[12.5px] mt-1 max-w-xs" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('servers.no_servers_filter_hint') }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- ── Grid view ── -->
            <template v-else>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div
                        v-for="(server, i) in filtered"
                        :key="server.id"
                        class="hc-reveal group relative rounded-2xl border overflow-hidden transition-all duration-200 hover:-translate-y-1"
                        :style="{ animationDelay: Math.min(i, 11) * 0.035 + 's' }"
                        :class="dark
                            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700 hover:shadow-xl hover:shadow-black/40'
                            : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-[0_10px_28px_rgba(0,0,0,0.10)] hover:border-zinc-300'"
                    >
                        <!-- Map artwork as the card header -->
                        <div class="relative h-[104px] overflow-hidden">
                            <img v-if="server.row_image && !failedRowImages.has(server.id)"
                                :src="server.row_image" alt="" loading="lazy" decoding="async"
                                class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.06]"
                                :class="dark ? 'opacity-70' : 'opacity-85'"
                                @error="failedRowImages.add(server.id)" />
                            <div v-else class="absolute inset-0"
                                :style="{ background: `linear-gradient(135deg, ${game.color}45, ${game.color}12)` }" />

                            <div class="absolute inset-0" aria-hidden="true"
                                :style="dark
                                    ? 'background: linear-gradient(to bottom, rgba(17,17,19,0.10) 0%, rgba(17,17,19,0.62) 55%, rgba(17,17,19,1) 100%)'
                                    : 'background: linear-gradient(to bottom, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.68) 55%, rgba(255,255,255,1) 100%)'" />

                            <!-- Players pill -->
                            <span v-if="server.status?.is_online"
                                class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[11.5px] font-bold tabular-nums text-white"
                                :style="{ backgroundColor: playerBarColor(server.status) }">
                                {{ server.status.players_online }}/{{ server.status.players_max }}
                            </span>
                            <span v-else
                                class="absolute top-3 right-3 px-2.5 py-1 rounded-md text-[10.5px] font-bold backdrop-blur-md"
                                :class="dark ? 'bg-zinc-900/80 text-zinc-400' : 'bg-white/85 text-zinc-600'">
                                {{ t('servers.offline') }}
                            </span>

                            <div class="absolute bottom-3 left-4 flex items-center gap-2">
                                <span class="w-9 h-9 rounded-lg overflow-hidden ring-2 shadow-lg"
                                    :class="dark ? 'ring-zinc-800' : 'ring-white'">
                                    <GameIcon :slug="game.slug" :alt="game.name" />
                                </span>
                                <span class="w-3 h-3 rounded-full border-2"
                                    :class="[
                                        server.status?.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-600' : 'bg-zinc-400',
                                        dark ? 'border-[#111113]' : 'border-white',
                                    ]"
                                    role="img"
                                    :aria-label="server.status?.is_online ? t('home.status_online') : t('home.status_offline')" />
                            </div>
                        </div>

                        <div class="px-4 pb-4 pt-2.5">
                            <Link :href="route('servers.show', { game: game.slug, ip: server.ip, port: server.port })"
                                class="block text-[14px] font-black truncate transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                                {{ server.name }}
                            </Link>

                            <div class="flex items-center gap-2 mt-1 text-[11.5px] font-mono min-w-0"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                <span class="truncate">{{ server.status?.map ?? '—' }}</span>
                                <span v-if="server.status?.ping != null" class="shrink-0" :class="pingClass(server.status)">
                                    · {{ server.status.ping }}ms
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 mt-3">
                                <span class="flex-1 min-w-0 text-[11.5px] font-mono truncate"
                                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ server.address }}</span>
                                <button type="button"
                                    class="w-7 h-7 flex items-center justify-center rounded-md transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="copiedId === server.id
                                        ? 'text-emerald-500'
                                        : dark ? 'text-zinc-600 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/70'"
                                    :aria-label="t('home.copy_address', { address: server.address })"
                                    :title="copiedId === server.id ? t('home.copied') : t('home.copy_address', { address: server.address })"
                                    @click="copyIp(server)">
                                    <component :is="copiedId === server.id ? Check : Copy" :size="13" :stroke-width="2" aria-hidden="true" />
                                </button>
                                <button type="button"
                                    class="w-7 h-7 flex items-center justify-center rounded-md transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 disabled:opacity-50"
                                    :class="server.is_favourited
                                        ? 'text-amber-400'
                                        : dark ? 'text-zinc-600 hover:text-amber-400 hover:bg-white/[0.06]' : 'text-zinc-400 hover:text-amber-500 hover:bg-zinc-200/70'"
                                    :aria-pressed="server.is_favourited"
                                    :aria-label="t(server.is_favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
                                    :disabled="favouriteLoading === server.id"
                                    @click="toggleFavourite(server)">
                                    <Star :size="14" :stroke-width="1.9" :fill="server.is_favourited ? 'currentColor' : 'none'" aria-hidden="true" />
                                </button>
                                <a :href="route('servers.connect', { game: game.slug, ip: server.ip, port: server.port })"
                                    class="w-7 h-7 flex items-center justify-center rounded-md transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-700 hover:bg-emerald-500/10'"
                                    :aria-label="t('home.connect_to', { name: server.name })"
                                    :title="t('home.connect_to', { name: server.name })">
                                    <Play :size="14" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <div v-if="!filtered.length" class="col-span-full flex flex-col items-center text-center px-6 py-16">
                        <span class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                            :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                            <Wifi :size="22" :stroke-width="1.5" />
                        </span>
                        <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                            {{ t('servers.no_servers') }}
                        </p>
                        <p class="text-[12.5px] mt-1 max-w-xs" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('servers.no_servers_filter_hint') }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- ── Statistics ──
                 Sits below the server list: someone landing here wants a server
                 first, and the history is context for that choice. -->
            <section class="hc-reveal mt-6 rounded-2xl border overflow-hidden"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
                :aria-label="t('servers.activity_title')">

                <div class="flex items-center justify-between gap-4 px-5 py-3 border-b"
                    :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                    <h2 class="text-[14px] font-black tracking-tight"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('servers.activity_title') }}
                    </h2>
                    <p class="text-[11.5px] shrink-0" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('servers.updated_at', { time: updatedLabel }) }}
                    </p>
                </div>

                <!-- Range tabs -->
                <div class="flex items-center gap-0.5 px-3 border-b overflow-x-auto"
                    :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'"
                    role="group" :aria-label="t('servers.range_group')">
                    <button v-for="tab in statTabs" :key="tab.key" type="button"
                        class="px-4 py-2.5 text-[12.5px] font-semibold border-b-2 -mb-px whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                        :class="activeStatTab === tab.key
                            ? 'text-blue-500 border-blue-500'
                            : dark ? 'text-zinc-500 border-transparent hover:text-zinc-200' : 'text-zinc-600 border-transparent hover:text-zinc-900'"
                        :aria-pressed="activeStatTab === tab.key"
                        @click="activeStatTab = tab.key">
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Maps -->
                <div v-if="activeStatTab === 'maps'" class="px-5 py-5">
                    <ul v-if="insights.maps.length" class="flex flex-col gap-2.5">
                        <li v-for="m in insights.maps" :key="m.map" class="flex items-center gap-3">
                            <span class="w-[150px] shrink-0 text-[12.5px] font-mono truncate"
                                :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ m.map }}</span>
                            <span class="flex-1 h-3.5 rounded-sm overflow-hidden"
                                :class="dark ? 'bg-zinc-800/70' : 'bg-zinc-100'">
                                <span class="hc-hero-bar block h-full rounded-r-[4px] bg-blue-500"
                                    :style="{ width: Math.max(2, m.share) + '%' }" />
                            </span>
                            <span class="w-10 shrink-0 text-right text-[12px] font-bold tabular-nums"
                                :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ m.share }}%</span>
                        </li>
                    </ul>
                    <p v-else class="text-[12.5px] py-6 text-center" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">
                        {{ t('servers.not_enough_data') }}
                    </p>
                    <p v-if="insights.maps.length" class="text-[11px] mt-3"
                       :class="dark ? 'text-zinc-600' : 'text-zinc-500'">
                        {{ t('servers.maps_hint') }}
                    </p>
                </div>

                <!-- Chart + figures -->
                <template v-else>
                    <div class="px-5 pt-5 pb-2">
                        <PlayerAreaChart
                            :data="activeRange.history"
                            :dark="dark"
                            :height="240"
                            :label="t('servers.players_over_time')"
                            :empty-label="t('servers.not_enough_data')"
                        />
                    </div>

                    <!-- Single values, so stat tiles rather than more charts. -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-px border-t"
                        :class="dark ? 'bg-zinc-800/60 border-zinc-800/60' : 'bg-zinc-200 border-zinc-200'">
                        <div v-for="tile in activityTiles" :key="tile.label"
                            class="px-5 py-4 text-center" :class="dark ? 'bg-[#111113]' : 'bg-white'"
                            :title="tile.hint">
                            <p class="text-[11.5px] font-semibold"
                               :class="dark ? 'text-zinc-500' : 'text-zinc-600'">{{ tile.label }}</p>
                            <p class="text-[26px] font-black leading-none tabular-nums mt-1.5"
                               :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                {{ tile.value }}<span v-if="tile.suffix" class="text-[15px] font-bold"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ tile.suffix }}</span>
                            </p>
                        </div>
                    </div>
                </template>
            </section>

        </div>
    </PublicLayout>
</template>
