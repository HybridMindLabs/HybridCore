<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Check, Heart, Search, RefreshCw, LayoutGrid, List, Wifi, Lock, Shield } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

interface ServerStatus {
    is_online: boolean; map: string | null; players_online: number;
    players_max: number; ping: number | null;
    is_password_protected: boolean; vac_secured: boolean;
}
interface GameServer {
    id: number; ip: string; port: number; address: string; name: string;
    country_code: string | null; tags: string[]; is_favourited: boolean;
    status: ServerStatus | null;
}
interface Game {
    id: number; name: string; slug: string; icon: string;
    color: string; cover_url: string | null;
}
interface Stats { total: number; online: number; players: number; }

const props = defineProps<{ game: Game; servers: GameServer[]; stats: Stats }>();

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

function copyIp(server: GameServer) {
    navigator.clipboard.writeText(server.address);
    copiedId.value = server.id;
    setTimeout(() => { copiedId.value = null; }, 1500);
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

function refresh() { router.reload({ only: ['servers', 'stats'] }); }

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
function playerColor(s: ServerStatus | null) {
    if (!s?.is_online) return dark.value ? '#52525b' : '#a1a1aa';
    const pct = s.players_online / (s.players_max || 1);
    if (pct >= 0.9) return '#ef4444';
    if (pct >= 0.6) return '#f59e0b';
    return '#22c55e';
}
</script>

<template>
    <Head :title="`${game.name} Servers`" />

    <PublicLayout>

        <!-- ── Hero Banner — blurred game image, KEEP! ── -->
        <div class="relative overflow-hidden" style="min-height: 210px">
            <div v-if="game.cover_url" class="absolute inset-0 overflow-hidden">
                <div
                    class="absolute inset-[-10%] bg-cover bg-center"
                    :style="{ backgroundImage: `url(${game.cover_url})`, filter: 'blur(16px) brightness(0.32) saturate(1.3)' }"
                />
            </div>
            <div v-else class="absolute inset-0" :style="{ background: `linear-gradient(135deg, ${game.color}40, ${game.color}10)` }" />

            <!-- Side vignette -->
            <div class="absolute inset-0" style="background: linear-gradient(to right, rgba(9,9,11,0.65) 0%, transparent 70%)" />
            <!-- Bottom fade — dynamic per theme -->
            <div
                class="absolute inset-0"
                :style="dark
                    ? 'background: linear-gradient(to bottom, transparent 40%, rgba(9,9,11,1) 100%)'
                    : 'background: linear-gradient(to bottom, transparent 35%, rgba(244,244,245,1) 100%)'"
            />
            <!-- Dot grid (same as Home hero) -->
            <div v-if="dark" class="absolute inset-0 pointer-events-none opacity-50"
                style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 pb-0">
                <Breadcrumb :items="[
                    { label: 'Home', href: route('home') },
                    { label: t('servers.title'), href: route('servers.index') },
                    { label: game.name },
                ]" />

                <!-- Game identity — icon KEPT! -->
                <div class="flex items-center gap-4 mb-5">
                    <div
                        class="w-14 h-14 rounded-xl shrink-0 shadow-lg border overflow-hidden"
                        :style="{ borderColor: game.color + '45' }"
                    >
                        <GameIcon :slug="game.slug" :alt="game.name" />
                    </div>
                    <div>
                        <h1
                            class="text-[26px] sm:text-[32px] font-black leading-tight tracking-tight"
                            :class="dark ? 'text-white' : 'text-zinc-900'"
                        >
                            {{ game.name }} {{ t('servers.title') }}
                        </h1>
                        <div class="flex items-center gap-4 mt-1.5 flex-wrap">
                            <span class="text-[13px]" :class="dark ? 'text-white/50' : 'text-zinc-400'">
                                <span class="font-semibold" :class="dark ? 'text-white' : 'text-zinc-700'">{{ stats.total.toLocaleString() }}</span>
                                {{ t('servers.servers_label').toLowerCase() }}
                            </span>
                            <span class="text-[13px]" :class="dark ? 'text-white/50' : 'text-zinc-400'">
                                <span class="font-semibold" :style="{ color: game.color }">{{ stats.online.toLocaleString() }}</span>
                                {{ t('servers.online_now').toLowerCase() }}
                            </span>
                            <span class="text-[13px]" :class="dark ? 'text-white/50' : 'text-zinc-400'">
                                <span class="font-semibold" :class="dark ? 'text-white' : 'text-zinc-700'">{{ stats.players.toLocaleString() }}</span>
                                {{ t('servers.players_label').toLowerCase() }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-0.5 border-b" :class="dark ? 'border-white/10' : 'border-zinc-200'">
                    <button
                        v-for="[key, label] in [['all', t('servers.filter_all')], ['favourites', t('servers.favourite')]]"
                        :key="key"
                        type="button"
                        class="px-5 py-3 text-[13px] font-semibold border-b-2 -mb-px transition-colors"
                        :class="activeTab === key
                            ? dark ? 'text-white border-white' : 'text-zinc-800 border-zinc-800'
                            : dark ? 'text-white/35 border-transparent hover:text-white/60' : 'text-zinc-400 border-transparent hover:text-zinc-600'"
                        @click="activeTab = key as any"
                    >{{ label }}</button>
                </div>
            </div>
        </div>

        <!-- ── Content ── -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-5">

            <!-- Filter chips -->
            <div class="flex items-center gap-1.5 mb-4 flex-wrap">
                <button
                    v-for="[key, label] in [
                        ['all', t('servers.filter_all')], ['online', t('servers.online')],
                        ['offline', t('servers.offline')], ['empty', t('servers.filter_empty')],
                        ['hasplayers', t('servers.filter_has_players')], ['full', t('servers.filter_full')],
                    ]"
                    :key="key"
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium border transition"
                    :class="activeFilter === key
                        ? 'text-white border-transparent'
                        : dark
                            ? 'border-zinc-800/80 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700 hover:bg-white/[0.04]'
                            : 'border-zinc-200 bg-white text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'"
                    :style="activeFilter === key ? { backgroundColor: game.color, borderColor: game.color } : {}"
                    @click="activeFilter = key as any"
                >
                    {{ label }}
                    <span
                        class="px-1.5 py-0.5 rounded-md text-[10px] font-semibold"
                        :class="activeFilter === key
                            ? 'bg-black/20 text-white'
                            : dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-100 text-zinc-500'"
                    >{{ counts[key as keyof typeof counts] }}</span>
                </button>
            </div>

            <!-- Toolbar -->
            <div class="flex items-center gap-3 mb-4 flex-wrap">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <Search :size="14" :stroke-width="1.8" class="absolute left-3.5 top-1/2 -translate-y-1/2" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" />
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('servers.search')"
                        class="w-full pl-9 pr-4 py-2.5 rounded-lg border text-[13px] transition focus:outline-none"
                        :class="dark
                            ? 'border-zinc-800/80 bg-[#111113] text-zinc-100 placeholder:text-zinc-600 focus:border-zinc-700'
                            : 'border-zinc-200 bg-white text-zinc-800 placeholder:text-zinc-400 focus:border-zinc-300'"
                    />
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <!-- View toggle -->
                    <div class="flex items-center rounded-lg border overflow-hidden" :class="dark ? 'border-zinc-800/80' : 'border-zinc-200'">
                        <button
                            v-for="[mode, icon, label] in [['table', List, t('servers.view_table')], ['grid', LayoutGrid, t('servers.view_grid')]]"
                            :key="mode"
                            type="button"
                            class="px-3 py-2.5 flex items-center gap-1.5 text-[12px] font-medium transition border-r last:border-0"
                            :class="[
                                viewMode === mode
                                    ? dark ? 'bg-zinc-800 text-zinc-100' : 'bg-zinc-100 text-zinc-800'
                                    : dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-700',
                                dark ? 'border-zinc-800/80' : 'border-zinc-200',
                            ]"
                            @click="viewMode = mode as any"
                        >
                            <component :is="icon" :size="13" :stroke-width="1.8" />
                            {{ label }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="flex items-center gap-1.5 px-3 py-2.5 rounded-lg border text-[12px] font-medium transition"
                        :class="dark
                            ? 'border-zinc-800/80 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700'
                            : 'border-zinc-200 text-zinc-500 hover:text-zinc-800'"
                        @click="refresh"
                    >
                        <RefreshCw :size="13" :stroke-width="1.8" />
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
                        <button class="text-left px-5 py-3 flex items-center gap-1 hover:text-current transition" @click="toggleSort('name')">
                            {{ t('servers.col_name') }} <span v-if="sortCol === 'name'" class="opacity-50">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                        </button>
                        <div class="px-4 py-3">{{ t('servers.col_connection') }}</div>
                        <button class="px-4 py-3 text-right flex items-center justify-end gap-1 hover:text-current transition" @click="toggleSort('players')">
                            {{ t('servers.col_online') }} <span v-if="sortCol === 'players'" class="opacity-50">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                        </button>
                        <button class="px-4 py-3 flex items-center gap-1 hover:text-current transition" @click="toggleSort('map')">
                            {{ t('servers.col_map') }} <span v-if="sortCol === 'map'" class="opacity-50">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                        </button>
                        <button class="px-4 py-3 text-right flex items-center justify-end gap-1 hover:text-current transition" @click="toggleSort('ping')">
                            {{ t('servers.col_ping') }} <span v-if="sortCol === 'ping'" class="opacity-50">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                        </button>
                        <div class="px-4 py-3" />
                    </div>

                    <!-- Rows -->
                    <Link
                        v-for="server in filtered"
                        :key="server.id"
                        :href="route('servers.show', { game: game.slug, ip: server.ip, port: server.port })"
                        class="grid grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_170px_110px_150px_80px_96px] border-b last:border-0 transition group"
                        :class="dark
                            ? 'border-zinc-800/50 hover:bg-white/[0.03]'
                            : 'border-zinc-100 hover:bg-zinc-50'"
                    >
                        <!-- Name -->
                        <div class="px-5 py-3.5 flex items-center gap-3 min-w-0">
                            <div
                                class="w-2 h-2 rounded-full shrink-0"
                                :class="server.status?.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-700' : 'bg-zinc-300'"
                            />
                            <span v-if="server.country_code" class="text-[14px] shrink-0">{{ countryFlag(server.country_code) }}</span>
                            <div class="min-w-0">
                                <p class="text-[13px] font-medium truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ server.name }}</p>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <Lock v-if="server.status?.is_password_protected" :size="10" :stroke-width="2" class="text-amber-400" />
                                    <Shield v-if="server.status?.vac_secured" :size="10" :stroke-width="2" class="text-emerald-400" />
                                    <span
                                        v-for="tag in server.tags.slice(0, 3)"
                                        :key="tag"
                                        class="text-[10px] font-medium px-1.5 py-0.5 rounded"
                                        :class="dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-100 text-zinc-400'"
                                    >{{ tag }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Connection -->
                        <div class="hidden lg:flex px-4 py-3.5 items-center gap-1.5 min-w-0" @click.prevent>
                            <span class="text-[11px] font-mono truncate flex-1" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ server.address }}</span>
                            <button
                                type="button"
                                class="p-1 rounded transition shrink-0"
                                :class="dark ? 'text-zinc-600 hover:text-zinc-200' : 'text-zinc-300 hover:text-zinc-600'"
                                :title="t('servers.copy_ip')"
                                @click.prevent="copyIp(server)"
                            >
                                <component :is="copiedId === server.id ? Check : Copy" :size="11" :stroke-width="2" :class="copiedId === server.id ? 'text-emerald-400' : ''" />
                            </button>
                        </div>

                        <!-- Online -->
                        <div class="hidden lg:flex px-4 py-3.5 flex-col items-end justify-center gap-1">
                            <span v-if="server.status?.is_online" class="text-[13px] font-semibold tabular-nums" :style="{ color: playerColor(server.status) }">
                                {{ server.status.players_online }}/{{ server.status.players_max }}
                            </span>
                            <span v-else class="text-[11px]" :class="dark ? 'text-zinc-700' : 'text-zinc-300'">{{ t('servers.offline').toLowerCase() }}</span>
                            <div v-if="server.status?.is_online" class="w-full h-1 rounded-full overflow-hidden" :class="dark ? 'bg-zinc-800' : 'bg-zinc-100'">
                                <div class="h-full rounded-full" :style="{ width: playerBarWidth(server.status), backgroundColor: playerBarColor(server.status) }" />
                            </div>
                        </div>

                        <!-- Map -->
                        <div class="hidden lg:flex px-4 py-3.5 items-center">
                            <span class="text-[12px] truncate" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ server.status?.map ?? '—' }}</span>
                        </div>

                        <!-- Ping -->
                        <div class="hidden lg:flex px-4 py-3.5 items-center justify-end gap-1">
                            <template v-if="server.status?.ping != null">
                                <Wifi :size="11" :stroke-width="1.8" class="text-emerald-400" />
                                <span class="text-[12px] font-medium tabular-nums" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ server.status.ping }}ms</span>
                            </template>
                            <span v-else class="text-[11px]" :class="dark ? 'text-zinc-800' : 'text-zinc-200'">—</span>
                        </div>

                        <!-- Actions -->
                        <div class="hidden lg:flex px-3 py-3.5 items-center justify-end gap-1.5" @click.prevent>
                            <button
                                type="button"
                                class="p-1.5 rounded-lg border transition"
                                :class="server.is_favourited
                                    ? 'border-red-500/30 text-red-400 bg-red-500/10'
                                    : dark ? 'border-zinc-800/80 text-zinc-600 hover:text-red-400 hover:border-red-500/30' : 'border-zinc-200 text-zinc-300 hover:text-red-400'"
                                :disabled="favouriteLoading === server.id"
                                @click.prevent="toggleFavourite(server)"
                            >
                                <Heart :size="12" :stroke-width="1.8" :fill="server.is_favourited ? 'currentColor' : 'none'" />
                            </button>
                            <a
                                :href="route('servers.connect', { game: game.slug, ip: server.ip, port: server.port })"
                                class="text-[11px] font-semibold px-2.5 py-1.5 rounded-lg transition"
                                :style="{ backgroundColor: game.color + '22', color: game.color, border: `1px solid ${game.color}40` }"
                                @click.stop
                            >{{ t('servers.connect') }}</a>
                        </div>
                    </Link>

                    <div v-if="!filtered.length" class="py-14 text-center">
                        <p class="text-[14px]" :class="dark ? 'text-zinc-700' : 'text-zinc-300'">{{ t('servers.no_servers') }}</p>
                    </div>
                </div>
            </template>

            <!-- ── Grid view ── -->
            <template v-else>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <Link
                        v-for="server in filtered"
                        :key="server.id"
                        :href="route('servers.show', { game: game.slug, ip: server.ip, port: server.port })"
                        class="rounded-xl border p-4 flex flex-col gap-3 transition"
                        :class="dark
                            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/70'
                            : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-md'"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div
                                    class="w-2 h-2 rounded-full shrink-0 mt-0.5"
                                    :class="server.status?.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-700' : 'bg-zinc-300'"
                                />
                                <p class="text-[13px] font-semibold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ server.name }}</p>
                            </div>
                            <span v-if="server.country_code" class="text-[14px] shrink-0">{{ countryFlag(server.country_code) }}</span>
                        </div>
                        <p class="text-[11px] font-mono" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ server.address }}</p>
                        <div class="flex items-center justify-between">
                            <span class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ server.status?.map ?? '—' }}</span>
                            <span v-if="server.status?.is_online" class="text-[12px] font-semibold" :style="{ color: playerColor(server.status) }">
                                {{ server.status.players_online }}/{{ server.status.players_max }}
                            </span>
                        </div>
                        <div v-if="server.status?.is_online" class="w-full h-1 rounded-full overflow-hidden" :class="dark ? 'bg-zinc-800' : 'bg-zinc-100'">
                            <div class="h-full rounded-full" :style="{ width: playerBarWidth(server.status), backgroundColor: playerBarColor(server.status) }" />
                        </div>
                    </Link>

                    <div v-if="!filtered.length" class="col-span-full py-14 text-center">
                        <p class="text-[14px]" :class="dark ? 'text-zinc-700' : 'text-zinc-300'">{{ t('servers.no_servers') }}</p>
                    </div>
                </div>
            </template>

        </div>
    </PublicLayout>
</template>

<script lang="ts">
function countryFlag(code: string): string {
    return code.toUpperCase().replace(/./g, c => String.fromCodePoint(c.charCodeAt(0) + 127397));
}
</script>
