<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, ArrowRight, Star } from '@lucide/vue';
import { ref, computed } from 'vue';
import GameThumbnail from '@/components/UI/GameThumbnail.vue';
import HomeServerRow from './HomeServerRow.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import type { GameFilter, GameStat, HomeServer } from './types';

const props = defineProps<{
    servers: HomeServer[];
    games: GameFilter[];
    gameStats: GameStat[];
    totalPlayers: number;
    maxPlayers: number;
    preferredGameSlugs: string[];
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const activeFilter = ref('all');

const gameFilters = computed(() => {
    const statBySlug = new Map(props.gameStats.map(g => [g.slug, g]));
    const preferred = props.preferredGameSlugs;
    // props.games is every active game on the platform, most with zero
    // servers — a tab strip listing all of them (vs. the handful that
    // actually host something) reads as broken/cluttered. Only tab games
    // that have at least one server; /servers already has the full catalog
    // for "what does this network support".
    const slugsWithServers = new Set(props.servers.map(s => s.game_slug));

    const filters: { key: string; label: string; players: number; max: number }[] = [
        { key: 'all', label: t('home.all_games'), players: props.totalPlayers, max: props.maxPlayers },
    ];

    // "My games" — only when the user picked games in Account → Preferences.
    if (preferred.length) {
        const mine = props.gameStats.filter(g => preferred.includes(g.slug));
        filters.push({
            key: 'mine',
            label: t('home.my_games'),
            players: mine.reduce((sum, g) => sum + g.players, 0),
            max: mine.reduce((sum, g) => sum + g.max_players, 0),
        });
    }

    filters.push(...props.games
        .filter(g => slugsWithServers.has(g.slug))
        .map(g => {
            const stat = statBySlug.get(g.slug);
            return { key: g.slug, label: g.name, players: stat?.players ?? 0, max: stat?.max_players ?? 0 };
        }));

    return filters;
});

function fillPercent(filter: { players: number; max: number }) {
    if (!filter.max) return 0;
    return Math.min(100, Math.round((filter.players / filter.max) * 100));
}

const filtered = computed(() => {
    if (activeFilter.value === 'all') return props.servers;
    if (activeFilter.value === 'mine') return props.servers.filter(s => props.preferredGameSlugs.includes(s.game_slug));
    return props.servers.filter(s => s.game_slug === activeFilter.value);
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-end justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-1 h-9 rounded-full bg-emerald-500 shrink-0 mt-0.5" aria-hidden="true" />
                <div class="min-w-0">
                    <h2 class="text-[20px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('home.servers_title') }}
                    </h2>
                    <p class="text-[12.5px] mt-0.5 leading-snug text-zinc-500">
                        {{ t('home.servers_hint') }}
                    </p>
                </div>
            </div>
            <Link :href="route('servers.index')"
                class="group hidden sm:flex items-center gap-1.5 text-[12px] font-semibold shrink-0 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded-lg px-1"
                :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                {{ t('home.browse_all') }}
                <ArrowRight :size="12" :stroke-width="2"
                    class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </Link>
        </div>

        <!-- Game filters. Each chip doubles as a capacity bar, so the
             list also answers "where is there room to play?". -->
        <div class="flex items-center gap-1 flex-wrap" role="group" :aria-label="t('home.filter_by_game')">
            <button v-for="filter in gameFilters" :key="filter.key" type="button"
                class="relative overflow-hidden flex flex-col px-3 pt-1.5 pb-2 text-[12px] rounded-lg border transition-colors font-medium"
                :class="activeFilter === filter.key
                    ? dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-400/40 bg-blue-50 text-blue-600'
                    : dark ? 'border-zinc-800/80 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700 hover:bg-white/[0.04]'
                           : 'border-zinc-200 bg-white text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'"
                :aria-pressed="activeFilter === filter.key"
                :title="t('home.filter_tooltip', {
                    players: filter.players.toLocaleString(),
                    max: filter.max.toLocaleString(),
                    percent: fillPercent(filter),
                })"
                @click="activeFilter = filter.key">
                <span class="flex items-center gap-1.5">
                    <Star v-if="filter.key === 'mine'" :size="12" :stroke-width="2" class="text-amber-400" />
                    <GameThumbnail v-else-if="filter.key !== 'all'" :game="filter.key" size="w-4 h-4 rounded-sm" />
                    {{ filter.label }}
                    <span class="text-[10px] font-semibold tabular-nums"
                        :class="filter.players > 0
                            ? (dark ? 'text-emerald-400' : 'text-emerald-800')
                            : 'text-zinc-500'">
                        {{ filter.players }}/{{ filter.max }}
                    </span>
                </span>
                <!-- Capacity bar -->
                <span class="absolute bottom-0 left-0 right-0 h-[3px]"
                    :class="dark ? 'bg-zinc-800/80' : 'bg-zinc-100'">
                    <span class="absolute inset-y-0 left-0 rounded-r-full transition-all duration-500"
                        :style="{
                            width: fillPercent(filter) + '%',
                            backgroundColor: fillPercent(filter) >= 90 ? '#ef4444' : fillPercent(filter) >= 60 ? '#f59e0b' : '#22c55e',
                        }" />
                </span>
            </button>
        </div>

        <!-- Server table -->
        <div class="rounded-xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

            <HomeServerRow v-for="server in filtered" :key="server.id" :server="server" />

            <div v-if="!filtered.length" class="flex flex-col items-center justify-center px-6 py-16 text-center">
                <span class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                    :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <Activity :size="22" :stroke-width="1.5" />
                </span>
                <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                    {{ t('home.no_servers') }}
                </p>
                <p class="text-[12.5px] mt-1 max-w-xs text-zinc-500">
                    {{ t('home.no_servers_hint') }}
                </p>
                <button v-if="activeFilter !== 'all'" type="button" @click="activeFilter = 'all'"
                    class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-[12.5px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="dark ? 'border-zinc-800 text-zinc-300 hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-500 hover:border-zinc-400 hover:bg-white'">
                    {{ t('home.show_all_games') }}
                </button>
            </div>
        </div>
    </div>
</template>
