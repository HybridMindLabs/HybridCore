<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Wifi } from '@lucide/vue';
import { computed, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ActivityPanel from '@/components/Servers/ActivityPanel.vue';
import GameHero from '@/components/Servers/GameHero.vue';
import GameServerCard from '@/components/Servers/GameServerCard.vue';
import GameServerRow from '@/components/Servers/GameServerRow.vue';
import GameServerToolbar from '@/components/Servers/GameServerToolbar.vue';
import { matchesFilter, type GameInfo, type GameServer, type Insights, type ServerFilter } from '@/components/Servers/types';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    game: GameInfo;
    servers: GameServer[];
    stats: { total: number; online: number; players: number };
    insights: Insights;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const search = ref('');
const activeFilter = ref<ServerFilter>('all');
const viewMode = ref<'table' | 'grid'>('table');
const activeTab = ref<'all' | 'favourites'>('all');

type SortColumn = 'name' | 'players' | 'map' | 'ping';
const sortCol = ref<SortColumn>('players');
const sortDir = ref<'asc' | 'desc'>('desc');

const FILTERS: ServerFilter[] = ['all', 'online', 'offline', 'empty', 'hasplayers', 'full'];
const counts = computed(() => Object.fromEntries(
    FILTERS.map((f) => [f, props.servers.filter((s) => matchesFilter(s, f)).length]),
) as Record<ServerFilter, number>);

const favouriteCount = computed(() => props.servers.filter((s) => s.is_favourited).length);

function sortValue(server: GameServer): number | string {
    switch (sortCol.value) {
        case 'players': return server.status?.players_online ?? -1;
        case 'ping': return server.status?.ping ?? 9999;
        case 'map': return server.status?.map ?? '';
        default: return server.name;
    }
}

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    const direction = sortDir.value === 'asc' ? 1 : -1;

    return props.servers
        .filter((s) => activeTab.value !== 'favourites' || s.is_favourited)
        .filter((s) => !q || s.name.toLowerCase().includes(q) || s.address.includes(q) || (s.status?.map ?? '').toLowerCase().includes(q))
        .filter((s) => matchesFilter(s, activeFilter.value))
        .sort((a, b) => {
            const va = sortValue(a);
            const vb = sortValue(b);
            return va < vb ? -direction : va > vb ? direction : 0;
        });
});

function toggleSort(col: SortColumn) {
    if (sortCol.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    else { sortCol.value = col; sortDir.value = 'desc'; }
}

const columns = computed<{ key: SortColumn | null; label: string; align: string }[]>(() => [
    { key: 'name', label: t('servers.col_name'), align: 'px-5' },
    { key: null, label: t('servers.col_connection'), align: 'px-4' },
    { key: 'map', label: t('servers.col_map'), align: 'px-4' },
    { key: 'players', label: t('servers.col_online'), align: 'px-4 justify-center' },
    { key: 'ping', label: t('servers.col_ping'), align: 'px-4 justify-end' },
]);

const updatedLabel = computed(() =>
    new Date(props.insights.updated_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'medium' }));

const refreshing = ref(false);
function refresh() {
    router.reload({
        only: ['servers', 'stats'],
        onStart: () => { refreshing.value = true; },
        onFinish: () => { refreshing.value = false; },
    });
}
</script>

<template>
    <Head>
        <title>{{ t('servers.game_servers_title', { game: game.name }) }}</title>
        <meta name="description" :content="t('servers.meta_game', {
            game: game.name,
            count: String(servers.length),
            online: String(counts.online),
        })" />
    </Head>

    <PublicLayout>
        <GameHero v-model:tab="activeTab" :game="game" :stats="stats"
            :total-count="servers.length" :favourite-count="favouriteCount" />

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-5">
            <GameServerToolbar v-model:search="search" v-model:filter="activeFilter" v-model:view="viewMode"
                :counts="counts" :result-count="filtered.length" :refreshing="refreshing" @refresh="refresh" />

            <!-- ── Table view ── -->
            <div v-if="viewMode === 'table'" class="rounded-xl border overflow-hidden"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                <!-- Header: same column template as the rows below -->
                <div class="hidden lg:grid grid-cols-[minmax(0,1fr)_240px_170px_104px_72px_84px] border-b text-[11px] font-semibold uppercase tracking-wider"
                    :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-500' : 'border-zinc-100 bg-zinc-50 text-zinc-400'">
                    <template v-for="col in columns" :key="col.label">
                        <button v-if="col.key" type="button"
                            class="py-3 uppercase tracking-wider flex items-center gap-1 hover:text-current transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                            :class="[col.align, sortCol === col.key ? (dark ? 'text-zinc-200' : 'text-zinc-900') : '']"
                            :aria-label="t('servers.sort_by', { column: col.label })"
                            @click="toggleSort(col.key)">
                            {{ col.label }}
                            <component v-if="sortCol === col.key" :is="sortDir === 'asc' ? ArrowUp : ArrowDown"
                                :size="11" :stroke-width="2.5" aria-hidden="true" />
                        </button>
                        <div v-else class="py-3" :class="col.align">{{ col.label }}</div>
                    </template>
                    <div class="px-4 py-3" />
                </div>

                <GameServerRow v-for="server in filtered" :key="server.id" :game="game" :server="server" />

                <div v-if="!filtered.length" class="flex flex-col items-center text-center px-6 py-16">
                    <span class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                        :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                        <Wifi :size="22" :stroke-width="1.5" />
                    </span>
                    <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ t('servers.no_servers') }}</p>
                    <p class="text-[12.5px] mt-1 max-w-xs text-zinc-500">{{ t('servers.no_servers_filter_hint') }}</p>
                </div>
            </div>

            <!-- ── Grid view ── -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <GameServerCard v-for="(server, i) in filtered" :key="server.id" :game="game" :server="server"
                    :style="{ animationDelay: Math.min(i, 11) * 0.035 + 's' }" />

                <div v-if="!filtered.length" class="col-span-full flex flex-col items-center text-center px-6 py-16">
                    <span class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                        :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                        <Wifi :size="22" :stroke-width="1.5" />
                    </span>
                    <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ t('servers.no_servers') }}</p>
                    <p class="text-[12.5px] mt-1 max-w-xs text-zinc-500">{{ t('servers.no_servers_filter_hint') }}</p>
                </div>
            </div>

            <!-- Below the list: someone landing here wants a server first, and
                 the history is context for that choice. -->
            <ActivityPanel class="hc-reveal mt-6" :ranges="insights.ranges" :maps="insights.maps"
                :title="t('servers.activity_title')" :subtitle="t('servers.updated_at', { time: updatedLabel })" />
        </div>
    </PublicLayout>
</template>
