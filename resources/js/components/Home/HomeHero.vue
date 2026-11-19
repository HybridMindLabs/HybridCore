<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Users, Server, Gamepad2, Wifi, Star, MousePointerClick } from '@lucide/vue';
import { computed } from 'vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { useCountUp } from '@/composables/useCountUp';
import type { GameStat, NetworkStats } from './types';

const props = defineProps<{
    stats: NetworkStats;
    gameStats: GameStat[];
    totalPlayers: number;
    maxPlayers: number;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const auth = computed(() => usePage<{ auth: { user: unknown } | null }>().props.auth);

const animatedPlayers = useCountUp(computed(() => props.totalPlayers));

const capacityPercent = computed(() => {
    if (!props.maxPlayers) return 0;
    return Math.min(100, Math.round((props.totalPlayers / props.maxPlayers) * 100));
});

// Each figure counts up independently so the strip animates as a group.
const animatedGames = useCountUp(computed(() => props.stats.games));
const animatedServers = useCountUp(computed(() => props.stats.servers));
const animatedMembers = useCountUp(computed(() => props.stats.members));

const networkStats = computed(() => [
    { icon: Gamepad2, value: animatedGames.value,   label: t('home.stat_games'),   hint: t('home.stat_games_hint') },
    { icon: Server,   value: animatedServers.value, label: t('home.stat_servers'), hint: t('home.stat_servers_hint') },
    { icon: Users,    value: animatedMembers.value, label: t('home.stat_members'), hint: t('home.stat_members_hint') },
    { icon: Wifi,     value: animatedPlayers.value, label: t('home.stat_players'), hint: t('home.stat_players_hint') },
]);

// Written for players deciding where to play, not for owners buying hosting.
const heroPerks = computed(() => [
    { icon: Users,             label: t('home.hero_perk_live'),    hint: t('home.hero_perk_live_hint') },
    { icon: MousePointerClick, label: t('home.hero_perk_connect'), hint: t('home.hero_perk_connect_hint') },
    { icon: Star,              label: t('home.hero_perk_save'),    hint: t('home.hero_perk_save_hint') },
]);

/** Busiest four games, with each bar scaled against the leader rather than
 *  against capacity — otherwise a quiet night flattens every bar to nothing. */
const topGames = computed(() => {
    const ranked = [...props.gameStats].filter(g => g.players > 0).sort((a, b) => b.players - a.players).slice(0, 4);
    const leader = ranked[0]?.players ?? 0;

    return ranked.map(g => ({
        ...g,
        share: leader ? Math.max(6, Math.round((g.players / leader) * 100)) : 0,
    }));
});
</script>

<template>
    <section
        class="hc-hero relative overflow-hidden border-b"
        :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
        :aria-label="t('home.hero_region')"
    >
        <!-- Ambient glows. Decorative only — hidden from assistive tech. -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
            <div class="hc-hero-glow absolute -top-32 left-1/4 w-[520px] h-[380px] rounded-full blur-[120px]"
                :class="dark ? 'bg-blue-500/8' : 'bg-blue-400/10'" />
            <div class="hc-hero-glow hc-hero-glow--slow absolute -top-16 right-1/4 w-[320px] h-[300px] rounded-full blur-[100px]"
                :class="dark ? 'bg-violet-500/6' : 'bg-violet-400/8'" />
            <div class="absolute inset-0"
                :class="dark ? 'opacity-50' : 'opacity-[0.35]'"
                :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />
        </div>

        <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-12 sm:py-16 lg:py-20">
            <div class="grid gap-10 lg:gap-14 lg:grid-cols-[minmax(0,1fr)_minmax(0,440px)] lg:items-center">

                <!-- ── Left: the pitch ── -->
                <div class="max-w-xl">
                    <!-- Live status. aria-live so screen readers hear it change. -->
                    <div
                        class="hc-hero-in inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                        :class="dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800'"
                        aria-live="polite"
                    >
                        <span class="hc-live-dot" aria-hidden="true" />
                        {{ t('home.servers_live', { online: stats.online_servers, total: stats.servers }) }}
                    </div>

                    <h1 class="hc-hero-in hc-hero-in--1 mt-6 text-[34px] sm:text-[46px] lg:text-[52px] font-black tracking-tight leading-[1.06]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('home.heading_1') }}<br>
                        <span class="hc-hero-gradient bg-clip-text text-transparent">
                            {{ t('home.heading_2') }}
                        </span>
                    </h1>

                    <p class="hc-hero-in hc-hero-in--2 mt-5 text-[15px] sm:text-[17px] leading-relaxed max-w-lg"
                       :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        {{ t('home.hero_description') }}
                    </p>

                    <div class="hc-hero-in hc-hero-in--3 flex items-center gap-3 mt-7 flex-wrap">
                        <Link :href="route('servers.index')"
                            class="group inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-[14px] px-6 py-3 rounded-xl transition shadow-lg shadow-blue-600/25 hover:shadow-blue-500/30 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 focus-visible:ring-offset-2"
                            :class="dark ? 'focus-visible:ring-offset-[#09090b]' : 'focus-visible:ring-offset-zinc-50'">
                            {{ t('home.browse_servers') }}
                            <ArrowRight :size="15" :stroke-width="2.2"
                                class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                        </Link>
                        <Link v-if="!auth" :href="route('register')"
                            class="inline-flex items-center font-bold text-[14px] px-6 py-3 rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-500 hover:border-zinc-400 hover:bg-white'">
                            {{ t('home.create_account') }}
                        </Link>
                    </div>

                    <!-- What a player actually gets here. -->
                    <ul class="hc-hero-in hc-hero-in--4 mt-8 grid gap-3 sm:grid-cols-3">
                        <li v-for="perk in heroPerks" :key="perk.label" class="flex items-start gap-2.5">
                            <span class="mt-0.5 w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                :class="dark ? 'bg-blue-500/10 text-blue-400' : 'bg-blue-500/10 text-blue-600'"
                                aria-hidden="true">
                                <component :is="perk.icon" :size="14" :stroke-width="2" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-bold leading-snug"
                                    :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ perk.label }}</span>
                                <span class="block text-[11.5px] leading-snug mt-0.5 text-zinc-500">{{ perk.hint }}</span>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- ── Right: what is happening right now ── -->
                <div class="hc-hero-in hc-hero-in--2 rounded-2xl border p-5 sm:p-6 backdrop-blur-sm"
                    :class="dark ? 'border-zinc-800/80 bg-zinc-900/50' : 'border-zinc-200 bg-white/80 shadow-[0_2px_12px_rgba(0,0,0,0.06)]'">

                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-[12px] font-bold uppercase tracking-widest"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ t('home.live_title') }}
                        </h2>
                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest"
                            :class="dark ? 'text-emerald-400' : 'text-emerald-800'">
                            <span class="hc-live-dot" aria-hidden="true" />{{ t('home.live_now') }}
                        </span>
                    </div>

                    <!-- Headline number: players in game right now -->
                    <p class="text-[13px] font-semibold mb-1.5 text-zinc-500">
                        {{ t('home.players_online') }}
                    </p>
                    <div class="flex items-end justify-between gap-3 mb-2.5">
                        <p class="text-[44px] font-black leading-none tabular-nums"
                           :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ animatedPlayers.toLocaleString() }}
                            <span class="text-[15px] font-bold"
                                  :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                / {{ maxPlayers.toLocaleString() }}
                            </span>
                        </p>
                        <p class="text-[13px] font-bold tabular-nums pb-1"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ capacityPercent }}%</p>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden"
                        :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'"
                        role="progressbar" :aria-valuenow="capacityPercent" aria-valuemin="0" aria-valuemax="100"
                        :aria-label="t('home.players_online')">
                        <div class="hc-hero-bar h-full rounded-full bg-gradient-to-r from-blue-500 to-violet-500"
                            :style="{ width: capacityPercent + '%' }" />
                    </div>

                    <!-- Busiest games -->
                    <div v-if="topGames.length" class="mt-6 pt-5 border-t"
                        :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                        <p class="text-[11px] font-bold uppercase tracking-widest mb-3 text-zinc-500">
                            {{ t('home.busiest_games') }}
                        </p>
                        <ul class="flex flex-col gap-1">
                            <li v-for="g in topGames" :key="g.slug">
                                <Link :href="route('servers.game', g.slug)"
                                    class="group flex items-center gap-3 rounded-xl px-2 py-2 -mx-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'hover:bg-white/[0.04]' : 'hover:bg-zinc-100'"
                                    :title="t('home.view_game_servers', { game: g.name })">
                                    <span class="w-7 h-7 rounded-lg overflow-hidden shrink-0 transition-transform group-hover:scale-105">
                                        <GameIcon :slug="g.slug" :alt="g.name" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-bold truncate transition-colors"
                                            :class="dark ? 'text-zinc-200 group-hover:text-white' : 'text-zinc-800 group-hover:text-zinc-900'">
                                            {{ g.name }}
                                        </span>
                                        <span class="mt-1 block h-1 rounded-full overflow-hidden"
                                            :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'" aria-hidden="true">
                                            <span class="hc-hero-bar block h-full rounded-full bg-gradient-to-r from-blue-500 to-violet-500"
                                                :style="{ width: g.share + '%' }" />
                                        </span>
                                    </span>
                                    <span class="text-right shrink-0">
                                        <span class="block text-[14px] font-black tabular-nums leading-none"
                                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ g.players }}</span>
                                        <span class="block text-[9px] font-bold uppercase tracking-widest mt-1 text-zinc-500">{{ t('home.players_suffix') }}</span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Network at a glance. Pulled out of the hero panel so neither block
         feels packed, and so the numbers get the full page width. ── -->
    <section class="border-b" :aria-label="t('home.stats_region')"
        :class="dark ? 'border-zinc-800/60 bg-[#0b0b0e]' : 'border-zinc-200 bg-white'">
        <dl class="max-w-[1600px] mx-auto px-4 sm:px-6 grid grid-cols-2 lg:grid-cols-4">
            <!--
                A <dl> may only group its children as <dt>/<dd> pairs, so the
                icon and the hint live inside the <dd> rather than beside it,
                and the term comes first in the DOM with `order` putting the
                figure back on top visually. pl-12 lines the text up under
                the value: w-9 (2.25rem) plus the gap-3 (0.75rem) beside it.
            -->
            <div v-for="(item, i) in networkStats" :key="item.label"
                class="hc-hero-in flex flex-col py-5 sm:py-6 lg:px-6 first:lg:pl-0 last:lg:pr-0"
                :style="{ animationDelay: 0.28 + i * 0.07 + 's' }"
                :class="[
                    dark ? 'border-zinc-800/60' : 'border-zinc-200',
                    i % 2 === 1 ? 'border-l pl-4 lg:pl-6' : '',
                    i < 2 ? 'border-b lg:border-b-0' : '',
                    i > 0 ? 'lg:border-l' : '',
                ]">
                <dt class="order-2 pl-12 text-[12px] font-bold mt-1.5"
                    :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ item.label }}</dt>
                <dd class="order-1 flex items-start gap-3 min-w-0">
                    <span class="mt-0.5 w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                        :class="dark ? 'bg-blue-500/10 text-blue-400' : 'bg-blue-500/10 text-blue-600'"
                        aria-hidden="true">
                        <component :is="item.icon" :size="16" :stroke-width="1.9" />
                    </span>
                    <span class="text-[22px] sm:text-[26px] font-black leading-none tabular-nums"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ item.value.toLocaleString() }}
                    </span>
                </dd>
                <dd class="order-3 pl-12 text-[11px] leading-snug mt-0.5 text-zinc-500">{{ item.hint }}</dd>
            </div>
        </dl>
    </section>
</template>
