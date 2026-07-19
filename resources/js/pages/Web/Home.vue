<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { Activity, Star, ArrowRight, Users, Server, Gamepad2, Wifi, ChevronLeft, ChevronRight, Newspaper, Eye, Clock, MousePointerClick, Copy, Check, Play, Pause } from '@lucide/vue';
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PublicSidebar from '@/components/UI/PublicSidebar.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import GameThumbnail from '@/components/UI/GameThumbnail.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { useCountUp } from '@/composables/useCountUp';

interface GameFilter { id: number; slug: string; name: string }
interface GameStat { slug: string; name: string; players: number; max_players: number }
interface HomeServer {
    id: number; name: string; game_slug: string; game_name: string; game_id: number;
    address: string;
    tags: string[]; is_online: boolean; map: string; map_image: string | null; row_image: string | null;
    players: number; max_players: number; ping: number;
    is_favourited: boolean; show_route: string; connect_route: string;
}

interface NewsCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    published_at: string; category: { id: number; name: string; slug: string; color: string } | null;
    author: { id: number; name: string } | null;
}

interface OnlineUser { id: number; name: string; username: string | null; avatar: string | null }

const props = defineProps<{
    servers: HomeServer[];
    games: GameFilter[];
    gameStats: GameStat[];
    totalPlayers: number;
    maxPlayers: number;
    viewer: { banner: string | null; role: { name: string; color: string } | null; achievements: string[] } | null;
    stats: { games: number; servers: number; members: number; online_servers: number };
    latestNews: NewsCard[];
    whoIsOnline: { users: OnlineUser[]; guests: number; bots: number } | null;
    activeToday: { users: OnlineUser[]; guests: number; bots: number } | null;
    communityActivity: { type: string; username: string | null; avatar: string | null; params: Record<string, string | number>; text?: string; at: string; url: string | null }[];
    preferredGameSlugs: string[];
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ app: { name: string }; auth: { user: any } | null; [key: string]: unknown }>();
const pageTitle = computed(() => `${page.props.app.name} - Gaming Network`);
const metaDescription = computed(() => t('home.meta_description', {
    servers: String(props.stats.servers),
    games: String(props.stats.games),
}));
const auth = computed(() => page.props.auth);

const activeFilter = ref('all');
const gameFilters = computed(() => {
    const statBySlug = new Map(props.gameStats.map(g => [g.slug, g]));
    const preferred = props.preferredGameSlugs;

    const filters: { key: string; label: string; players: number; max: number }[] = [
        { key: 'all', label: t('home.all_games'), players: props.totalPlayers, max: props.maxPlayers },
    ];

    // "My games" — only when the user picked favourite games in onboarding.
    if (preferred.length) {
        const mine = props.gameStats.filter(g => preferred.includes(g.slug));
        filters.push({
            key: 'mine',
            label: t('home.my_games'),
            players: mine.reduce((sum, g) => sum + g.players, 0),
            max: mine.reduce((sum, g) => sum + g.max_players, 0),
        });
    }

    filters.push(...props.games.map(g => {
        const stat = statBySlug.get(g.slug);
        return { key: g.slug, label: g.name, players: stat?.players ?? 0, max: stat?.max_players ?? 0 };
    }));

    return filters;
});

// ── Hero ─────────────────────────────────────────────────────────
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

/** Busiest three games, with each bar scaled against the leader rather than
 *  against capacity — otherwise a quiet night flattens every bar to nothing. */
const topGames = computed(() => {
    const ranked = [...props.gameStats].filter(g => g.players > 0).sort((a, b) => b.players - a.players).slice(0, 4);
    const leader = ranked[0]?.players ?? 0;

    return ranked.map(g => ({
        ...g,
        share: leader ? Math.max(6, Math.round((g.players / leader) * 100)) : 0,
    }));
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

// ── News ─────────────────────────────────────────────────────────
const lead = computed(() => props.latestNews[0] ?? null);
const rest = computed(() => props.latestNews.slice(1, 4));

// ── Featured slider ──────────────────────────────────────────────
const featured = computed(() => props.servers.filter(s => s.is_online).slice(0, 6));
const failedMapImages = reactive(new Set<number>());
const slideIndex = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

// WCAG 2.2.2: content that moves on its own for more than five seconds needs a
// way to stop it. Hover and keyboard focus pause it, there is an explicit
// toggle, and it never auto-starts for people who asked for reduced motion.
const reducedMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

const autoplay = ref(!reducedMotion);
const sliderHovered = ref(false);

function nextSlide() { slideIndex.value = (slideIndex.value + 1) % featured.value.length; }
function prevSlide() { slideIndex.value = (slideIndex.value - 1 + featured.value.length) % featured.value.length; }
function goToSlide(i: number) { slideIndex.value = i; }

function resetTimer() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
    if (!autoplay.value || sliderHovered.value || featured.value.length < 2) return;
    timer = setInterval(nextSlide, 5000);
}

watch([autoplay, sliderHovered, featured], resetTimer);
onMounted(resetTimer);
onUnmounted(() => { if (timer) clearInterval(timer); });

// ── Presence ─────────────────────────────────────────────────────
// The two blocks were ~55 lines of duplicated markup; they only differ by
// source, accent and empty text.
const presenceGroups = computed(() => [
    {
        key: 'online',
        label: t('home.who_is_online'),
        hint: t('home.last_5_minutes'),
        data: props.whoIsOnline,
        empty: t('home.no_registered_online'),
        live: true,
        countClass: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    },
    {
        key: 'today',
        label: t('home.active_last_24h'),
        hint: '',
        data: props.activeToday,
        empty: t('home.no_active_users'),
        live: false,
        countClass: 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    },
]);

/** Guards against an empty display name, which would throw on [0]. */
function initial(name: string): string {
    return (name?.trim()?.[0] ?? '?').toUpperCase();
}

const gameColors: Record<string, string> = {
    'cs2': '#f97316', 'csgo': '#f97316', 'css': '#eab308',
    'garrysmod': '#a78bfa', 'tf2': '#ef4444', 'minecraft': '#22c55e',
    'valheim': '#06b6d4', 'rust': '#b45309', 'dod': '#3b82f6',
};
function gameColor(slug: string) { return gameColors[slug] ?? '#3b82f6'; }

/** Blue when quiet, green once it has a crowd, amber filling up, red when full. */
function serverFillColor(server: HomeServer): string {
    if (!server.is_online || !server.max_players) return '#71717a';
    const pct = (server.players / server.max_players) * 100;
    if (pct >= 95) return '#ef4444';
    if (pct >= 75) return '#f59e0b';
    if (pct >= 25) return '#22c55e';
    return '#3b82f6';
}

// ── Copy address ─────────────────────────────────────────────────
const copiedId = ref<number | null>(null);
let copyTimer: ReturnType<typeof setTimeout> | null = null;

async function copyAddress(server: HomeServer) {
    try {
        await navigator.clipboard.writeText(server.address);
    } catch {
        // Clipboard API needs a secure context; fall back to a temporary field.
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

onUnmounted(() => { if (copyTimer) clearTimeout(copyTimer); });

function pingColor(ping: number) {
    if (ping < 50)  return dark.value ? 'text-emerald-400' : 'text-emerald-700';
    if (ping < 100) return dark.value ? 'text-amber-400'   : 'text-amber-700';
    return 'text-red-400';
}

function toggleFavourite(server: HomeServer) {
    if (!page.props.auth?.user) { router.visit(route('login')); return; }
    const previous = server.is_favourited;
    server.is_favourited = !previous; // optimistic — rolled back onError
    router.post(route('servers.favourite', server.id), {}, {
        preserveScroll: true, preserveState: true,
        onError: () => { server.is_favourited = previous; },
    });
}
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta name="description" :content="metaDescription" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
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
                            :class="dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700'"
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
                           :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
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
                                :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-white'">
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
                                    <span class="block text-[11.5px] leading-snug mt-0.5"
                                        :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ perk.hint }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- ── Right: what is happening right now ── -->
                    <div class="hc-hero-in hc-hero-in--2 rounded-2xl border p-5 sm:p-6 backdrop-blur-sm"
                        :class="dark ? 'border-zinc-800/80 bg-zinc-900/50' : 'border-zinc-200 bg-white/80 shadow-[0_2px_12px_rgba(0,0,0,0.06)]'">

                        <div class="flex items-center justify-between mb-5">
                            <h2 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                                {{ t('home.live_title') }}
                            </h2>
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-emerald-400' : 'text-emerald-700'">
                                <span class="hc-live-dot" aria-hidden="true" />{{ t('home.live_now') }}
                            </span>
                        </div>

                        <!-- Headline number: players in game right now -->
                        <p class="text-[13px] font-semibold mb-1.5"
                           :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('home.players_online') }}
                        </p>
                        <div class="flex items-end justify-between gap-3 mb-2.5">
                            <p class="text-[44px] font-black leading-none tabular-nums"
                               :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                {{ animatedPlayers.toLocaleString() }}
                                <span class="text-[15px] font-bold"
                                      :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                    / {{ maxPlayers.toLocaleString() }}
                                </span>
                            </p>
                            <p class="text-[13px] font-bold tabular-nums pb-1"
                               :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ capacityPercent }}%</p>
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
                            <p class="text-[11px] font-bold uppercase tracking-widest mb-3"
                               :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
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
                                            <span class="block text-[9px] font-bold uppercase tracking-widest mt-1"
                                                :class="dark ? 'text-zinc-600' : 'text-zinc-500'">{{ t('home.players_suffix') }}</span>
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
                        :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ item.label }}</dt>
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
                    <dd class="order-3 pl-12 text-[11px] leading-snug mt-0.5"
                        :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.hint }}</dd>
                </div>
            </dl>
        </section>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-6">
            <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)_300px] gap-6">

                <!-- Sidebar -->
                <div class="xl:order-first order-last flex flex-col gap-4">
                    <PublicSidebar :viewer="viewer" :stats="stats" :community-activity="communityActivity" />
                    <ExtensionSlot name="home.left.bottom" />
                </div>

                <!-- Middle column: News + Servers -->
                <div class="min-w-0 flex flex-col gap-6">
                    <ExtensionSlot name="home.middle.top" />

                    <!-- ══════════════════════════ LATEST NEWS -->
                    <template v-if="latestNews.length">
                        <div>
                            <!-- Header -->
                            <div class="flex items-end justify-between gap-4 mb-4">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="w-1 h-9 rounded-full bg-blue-500 shrink-0 mt-0.5" aria-hidden="true" />
                                    <div class="min-w-0">
                                        <h2 class="text-[20px] font-black tracking-tight"
                                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('home.latest_news') }}</h2>
                                        <p class="text-[12.5px] mt-0.5 leading-snug"
                                           :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('home.news_hint') }}</p>
                                    </div>
                                </div>
                                <!-- Always visible: on mobile this used to be hidden, leaving
                                     the bottom button as the only route to the archive. -->
                                <Link :href="route('news.index')"
                                    class="group flex items-center gap-1.5 text-[12px] font-semibold shrink-0 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded-lg px-1"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                                    {{ t('home.all_articles') }}
                                    <ArrowRight :size="12" :stroke-width="2"
                                        class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                                </Link>
                            </div>

                            <div class="grid gap-4 md:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">

                                <!-- Lead story: the newest article gets the room to be read. -->
                                <Link v-if="lead" :href="route('news.show', lead.slug)"
                                    class="hc-reveal group flex flex-col rounded-xl border overflow-hidden transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-[0_6px_20px_rgba(0,0,0,0.09)]'">

                                    <div class="relative aspect-[16/9] overflow-hidden"
                                        :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
                                        <img v-if="lead.featured_image_url" :src="lead.featured_image_url"
                                            :alt="lead.title" loading="lazy" decoding="async"
                                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.04]" />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <Newspaper :size="28" :stroke-width="1.2"
                                                :class="dark ? 'text-zinc-700' : 'text-zinc-300'" aria-hidden="true" />
                                        </div>

                                        <span v-if="lead.category"
                                            class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-bold uppercase tracking-wide backdrop-blur-md"
                                            :style="{ background: lead.category.color + '26', color: lead.category.color, boxShadow: `inset 0 0 0 1px ${lead.category.color}45` }">
                                            {{ lead.category.name }}
                                        </span>
                                    </div>

                                    <div class="flex flex-col flex-1 p-4">
                                        <h3 class="text-[17px] font-black leading-snug line-clamp-2 transition-colors"
                                            :class="dark ? 'text-zinc-100 group-hover:text-blue-300' : 'text-zinc-900 group-hover:text-blue-700'">
                                            {{ lead.title }}
                                        </h3>
                                        <p v-if="lead.excerpt" class="text-[13px] leading-relaxed line-clamp-2 mt-1.5"
                                            :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                                            {{ lead.excerpt }}
                                        </p>
                                        <div class="flex items-center gap-3 mt-4 pt-3 border-t text-[11px]"
                                            :class="dark ? 'border-zinc-800/60 text-zinc-500' : 'border-zinc-200 text-zinc-500'">
                                            <span v-if="lead.author" class="font-semibold truncate">{{ lead.author.name }}</span>
                                            <span class="flex items-center gap-1 shrink-0" :title="t('home.views_hint')">
                                                <Eye :size="11" :stroke-width="1.8" aria-hidden="true" />{{ lead.views }}
                                            </span>
                                            <span class="flex items-center gap-1 shrink-0">
                                                <Clock :size="11" :stroke-width="1.8" aria-hidden="true" />
                                                {{ t('news.read_time_short', { m: lead.reading_time }) }}
                                            </span>
                                            <span class="ml-auto shrink-0">{{ lead.published_at }}</span>
                                        </div>
                                    </div>
                                </Link>

                                <!-- The rest, compact -->
                                <div class="hc-reveal flex flex-col rounded-xl border overflow-hidden divide-y" style="animation-delay:0.08s"
                                    :class="dark ? 'border-zinc-800/70 bg-[#111113] divide-zinc-800/50' : 'border-zinc-200 bg-white divide-zinc-100 shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                                    <Link v-for="a in rest" :key="a.id"
                                        :href="route('news.show', a.slug)"
                                        class="group flex gap-3.5 p-3.5 flex-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                                        :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50'">

                                        <div class="relative shrink-0 w-[92px] h-[68px] rounded-lg overflow-hidden"
                                            :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
                                            <img v-if="a.featured_image_url" :src="a.featured_image_url"
                                                :alt="a.title" loading="lazy" decoding="async"
                                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                            <div v-else class="w-full h-full flex items-center justify-center">
                                                <Newspaper :size="16" :stroke-width="1.3"
                                                    :class="dark ? 'text-zinc-700' : 'text-zinc-300'" aria-hidden="true" />
                                            </div>
                                            <div v-if="a.category" class="absolute bottom-0 left-0 right-0 h-0.5"
                                                :style="{ background: a.category.color }" aria-hidden="true" />
                                        </div>

                                        <div class="min-w-0 flex flex-col flex-1">
                                            <span v-if="a.category" class="text-[10.5px] font-bold uppercase tracking-wide mb-1"
                                                :style="{ color: a.category.color }">
                                                {{ a.category.name }}
                                            </span>
                                            <h3 class="text-[13.5px] font-bold leading-snug line-clamp-2 transition-colors"
                                                :class="dark ? 'text-zinc-100 group-hover:text-blue-300' : 'text-zinc-900 group-hover:text-blue-700'">
                                                {{ a.title }}
                                            </h3>
                                            <div class="flex items-center gap-2.5 mt-auto pt-2 text-[10.5px]"
                                                :class="dark ? 'text-zinc-600' : 'text-zinc-500'">
                                                <span class="flex items-center gap-1">
                                                    <Clock :size="10" :stroke-width="1.8" aria-hidden="true" />
                                                    {{ t('news.read_time_short', { m: a.reading_time }) }}
                                                </span>
                                                <span class="ml-auto">{{ a.published_at }}</span>
                                            </div>
                                        </div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </template>
                    <!-- ══════════════════════ END LATEST NEWS -->

                    <!-- ══════════════════════════ SERVERS -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-end justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="w-1 h-9 rounded-full bg-emerald-500 shrink-0 mt-0.5" aria-hidden="true" />
                                <div class="min-w-0">
                                    <h2 class="text-[20px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                        {{ t('home.servers_title') }}
                                    </h2>
                                    <p class="text-[12.5px] mt-0.5 leading-snug" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
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
                                            ? 'text-emerald-500'
                                            : dark ? 'text-zinc-700' : 'text-zinc-300'">
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

                            <div v-for="server in filtered" :key="server.id"
                                class="hc-server-row group relative flex items-center gap-3 px-3 sm:px-4 py-2.5 border-b last:border-0 overflow-hidden transition-colors"
                                :class="dark ? 'border-zinc-800/50 hover:bg-white/[0.03]' : 'border-zinc-100 hover:bg-zinc-100/70'">

                                <!-- Current map, bleeding in from the right and faded out so it
                                     stays behind the text. Decorative — the map name is in the row. -->
                                <div v-if="server.row_image && !failedMapImages.has(server.id)"
                                    class="absolute inset-y-0 right-0 w-[55%] pointer-events-none hidden sm:block"
                                    aria-hidden="true">
                                    <img :src="server.row_image" alt="" loading="lazy" decoding="async"
                                        class="hc-server-map w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                        :class="dark ? 'opacity-[0.45] group-hover:opacity-[0.6]' : 'opacity-[0.22] group-hover:opacity-[0.32]'"
                                        @error="failedMapImages.add(server.id)" />
                                </div>

                                <!-- Accent edge in the game's colour -->
                                <span class="absolute left-0 inset-y-0 w-[3px] transition-opacity"
                                    :style="{ background: gameColor(server.game_slug) }"
                                    :class="server.is_online ? 'opacity-100' : 'opacity-30'"
                                    aria-hidden="true" />

                                <!-- Game icon with an online dot -->
                                <div class="relative shrink-0 ml-1">
                                    <span class="block w-9 h-9 rounded-lg overflow-hidden">
                                        <GameIcon :slug="server.game_slug" :alt="server.game_name" />
                                    </span>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                                        :class="[
                                            server.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-600' : 'bg-zinc-400',
                                            dark ? 'border-[#111113]' : 'border-white',
                                        ]"
                                        role="img"
                                        :aria-label="server.is_online ? t('home.status_online') : t('home.status_offline')"
                                        :title="server.is_online ? t('home.status_online') : t('home.status_offline')" />
                                </div>

                                <!-- Name + map -->
                                <div class="relative min-w-0 flex-1">
                                    <Link :href="server.show_route"
                                        class="block text-[13.5px] font-bold truncate transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded"
                                        :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                                        {{ server.name }}
                                    </Link>
                                    <p class="text-[11.5px] font-mono truncate mt-0.5"
                                       :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                        {{ server.map }}
                                        <span class="lg:hidden" :class="pingColor(server.ping)"> · {{ server.ping }}ms</span>
                                    </p>
                                </div>

                                <!-- Address + copy -->
                                <div class="relative hidden lg:flex items-center gap-1.5 shrink-0">
                                    <span class="text-[12.5px] font-mono tabular-nums"
                                          :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ server.address }}</span>
                                    <button type="button"
                                        class="w-7 h-7 flex items-center justify-center rounded-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                        :class="copiedId === server.id
                                            ? 'text-emerald-500'
                                            : dark ? 'text-zinc-600 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200/70'"
                                        :aria-label="t('home.copy_address', { address: server.address })"
                                        :title="copiedId === server.id ? t('home.copied') : t('home.copy_address', { address: server.address })"
                                        @click="copyAddress(server)">
                                        <Check v-if="copiedId === server.id" :size="14" :stroke-width="2.4" aria-hidden="true" />
                                        <Copy v-else :size="14" :stroke-width="1.9" aria-hidden="true" />
                                    </button>
                                </div>

                                <!-- Ping -->
                                <span class="relative hidden lg:block text-[12.5px] font-mono tabular-nums w-14 text-right shrink-0"
                                      :class="pingColor(server.ping)">{{ server.ping }}ms</span>

                                <!-- Connect -->
                                <a :href="server.connect_route"
                                    class="relative shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'text-zinc-500 hover:text-emerald-400 hover:bg-emerald-500/10' : 'text-zinc-500 hover:text-emerald-700 hover:bg-emerald-500/10'"
                                    :aria-label="t('home.connect_to', { name: server.name })"
                                    :title="t('home.connect_to', { name: server.name })">
                                    <Play :size="15" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                                </a>

                                <!-- Players pill, coloured by how full the server is -->
                                <span class="relative shrink-0 px-2.5 py-1 rounded-md text-[12px] font-bold tabular-nums text-white min-w-[62px] text-center"
                                    :style="{ backgroundColor: serverFillColor(server) }"
                                    :title="t('home.filter_tooltip', {
                                        players: server.players,
                                        max: server.max_players,
                                        percent: server.max_players ? Math.round(server.players / server.max_players * 100) : 0,
                                    })">
                                    {{ server.players }}/{{ server.max_players }}
                                </span>

                                <!-- Favourite -->
                                <button type="button"
                                    class="relative shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="server.is_favourited
                                        ? 'text-amber-400'
                                        : dark ? 'text-zinc-600 hover:text-amber-400 hover:bg-white/[0.06]' : 'text-zinc-400 hover:text-amber-500 hover:bg-zinc-200/70'"
                                    :aria-pressed="server.is_favourited"
                                    :aria-label="t(server.is_favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
                                    :title="t(server.is_favourited ? 'home.unfavourite_server' : 'home.favourite_server', { name: server.name })"
                                    @click="toggleFavourite(server)">
                                    <Star :size="15" :stroke-width="1.9" :fill="server.is_favourited ? 'currentColor' : 'none'" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="!filtered.length" class="flex flex-col items-center justify-center px-6 py-16 text-center">
                                <span class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                                    :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                                    <Activity :size="22" :stroke-width="1.5" />
                                </span>
                                <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                    {{ t('home.no_servers') }}
                                </p>
                                <p class="text-[12.5px] mt-1 max-w-xs" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    {{ t('home.no_servers_hint') }}
                                </p>
                                <button v-if="activeFilter !== 'all'" type="button" @click="activeFilter = 'all'"
                                    class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-[12.5px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'border-zinc-800 text-zinc-300 hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-white'">
                                    {{ t('home.show_all_games') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- ══════════════════════ END SERVERS -->
                    <ExtensionSlot name="home.middle.bottom" />
                </div>

                <!-- ── Right column ──
                     Visible from lg and stacked below the content on smaller
                     screens. It used to be `hidden xl:flex`, which silently
                     dropped the featured servers and the presence lists for
                     everyone under 1280px. -->
                <div class="flex flex-col gap-3">
                    <ExtensionSlot name="home.right.top" />

                    <section :aria-label="t('home.featured_servers')"
                        aria-roledescription="carousel"
                        @mouseenter="sliderHovered = true"
                        @mouseleave="sliderHovered = false"
                        @focusin="sliderHovered = true"
                        @focusout="sliderHovered = false">

                        <div class="flex items-center justify-between gap-2 mb-2.5">
                            <div class="min-w-0">
                                <h3 class="text-[11px] font-black uppercase tracking-widest"
                                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                                    {{ t('home.featured_servers') }}
                                </h3>
                                <p class="text-[11px] mt-0.5 leading-snug" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    {{ t('home.featured_hint') }}
                                </p>
                            </div>

                            <div v-if="featured.length > 1" class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="autoplay = !autoplay"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'"
                                    :aria-label="autoplay ? t('home.pause_rotation') : t('home.resume_rotation')"
                                    :title="autoplay ? t('home.pause_rotation') : t('home.resume_rotation')">
                                    <Pause v-if="autoplay" :size="12" :stroke-width="2.2" aria-hidden="true" />
                                    <Play v-else :size="12" :stroke-width="2.2" aria-hidden="true" />
                                </button>
                                <button type="button" @click="prevSlide"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'"
                                    :aria-label="t('home.previous_server')" :title="t('home.previous_server')">
                                    <ChevronLeft :size="13" :stroke-width="2.2" aria-hidden="true" />
                                </button>
                                <button type="button" @click="nextSlide"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'"
                                    :aria-label="t('home.next_server')" :title="t('home.next_server')">
                                    <ChevronRight :size="13" :stroke-width="2.2" aria-hidden="true" />
                                </button>
                            </div>
                        </div>

                    <!-- Cards -->
                    <div class="relative" style="min-height: 340px"
                        aria-live="polite" :aria-atomic="false">
                        <template v-for="(server, i) in featured" :key="server.id">
                            <Transition
                                enter-active-class="transition-all duration-500 ease-out"
                                enter-from-class="opacity-0 translate-y-3"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition-all duration-300 ease-in"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 -translate-y-3"
                            >
                                <div v-if="i === slideIndex"
                                    class="absolute inset-0 rounded-2xl border overflow-hidden"
                                    :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_2px_12px_rgba(0,0,0,0.08)]'"
                                >
                                    <!-- Header: map screenshot when available, game-colored glow otherwise -->
                                    <div class="relative h-[120px] overflow-hidden flex items-end p-4"
                                        :style="server.map_image && !failedMapImages.has(server.id)
                                            ? undefined
                                            : `background: linear-gradient(135deg, ${gameColor(server.game_slug)}18 0%, ${gameColor(server.game_slug)}06 100%)`">
                                        <template v-if="server.map_image && !failedMapImages.has(server.id)">
                                            <img :src="server.map_image" :alt="server.map"
                                                class="absolute inset-0 w-full h-full object-cover"
                                                @error="failedMapImages.add(server.id)" />
                                        </template>
                                        <div v-else class="absolute -top-8 -right-8 w-40 h-40 rounded-full blur-3xl opacity-25"
                                            :style="`background: ${gameColor(server.game_slug)}`" />
                                        <div class="absolute inset-0"
                                            :class="dark ? 'bg-gradient-to-t from-[#111113] via-[#111113]/40 to-transparent' : 'bg-gradient-to-t from-white via-white/40 to-transparent'" />

                                        <!-- Game icon -->
                                        <div class="absolute top-3.5 right-4 w-14 h-14 rounded-xl overflow-hidden border"
                                            :class="dark ? 'border-zinc-800' : 'border-zinc-200 shadow-sm'">
                                            <GameIcon :slug="server.game_slug" :alt="server.game_name" />
                                        </div>

                                        <!-- Status -->
                                        <div class="relative z-10">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="w-1.5 h-1.5 rounded-full" :class="server.is_online ? 'bg-emerald-400' : 'bg-zinc-500'" />
                                                <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                                    {{ server.is_online ? t('home.status_online') : t('home.status_offline') }}
                                                </span>
                                            </div>
                                            <p class="text-[11px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ server.game_name }}</p>
                                        </div>
                                    </div>

                                    <!-- Body -->
                                    <div class="p-4">
                                        <Link :href="server.show_route"
                                            class="block text-[15px] font-black tracking-tight truncate mb-4 transition-colors"
                                            :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-900 hover:text-blue-600'">
                                            {{ server.name }}
                                        </Link>

                                        <div class="flex flex-col gap-2.5 mb-4">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ t('home.col_map') }}</span>
                                                <span class="text-[12px] font-mono truncate max-w-[160px]" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ server.map }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-[10px] font-bold uppercase tracking-widest shrink-0" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ t('home.col_players') }}</span>
                                                <div class="flex items-center gap-2 flex-1 justify-end">
                                                    <div class="h-1 flex-1 max-w-[80px] rounded-full overflow-hidden" :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'">
                                                        <div class="h-full rounded-full"
                                                            :style="`width:${server.max_players > 0 ? Math.round((server.players / server.max_players) * 100) : 0}%; background:${gameColor(server.game_slug)}`" />
                                                    </div>
                                                    <span class="text-[12px] font-mono shrink-0" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ server.players }}/{{ server.max_players }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ t('home.col_ping') }}</span>
                                                <span class="text-[12px] font-mono" :class="pingColor(server.ping)">{{ server.ping }}ms</span>
                                            </div>
                                        </div>

                                        <!-- Connect -->
                                        <a :href="server.connect_route"
                                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-bold border transition-colors"
                                            :style="`color: ${gameColor(server.game_slug)}; border-color: ${gameColor(server.game_slug)}30; background: ${gameColor(server.game_slug)}12`"
                                        >
                                            {{ t('home.connect') }} <ArrowRight :size="13" :stroke-width="2.2" />
                                        </a>
                                    </div>
                                </div>
                            </Transition>
                        </template>
                    </div>

                    <!-- Dot indicators -->
                    <!--
                        The dot is 6px, which is far under the 24x24 minimum for
                        a touch target. The button carries the full 24x24 and the
                        dot is drawn inside it, so the target grows without the
                        indicator getting heavier. That does space the dots out —
                        24px apart rather than 12 — which is the cost of them
                        being reliably tappable.
                    -->
                    <div v-if="featured.length > 1" class="flex items-center justify-center pt-1">
                        <button v-for="(s, i) in featured" :key="s.id" type="button"
                            class="group grid place-items-center w-6 h-6 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :aria-label="t('home.go_to_server', { name: s.name })"
                            :aria-current="i === slideIndex ? 'true' : undefined"
                            :title="s.name"
                            @click="goToSlide(i)"
                        >
                            <span class="rounded-full transition-all duration-300"
                                :class="i === slideIndex
                                    ? 'w-5 h-1.5 bg-blue-500'
                                    : dark ? 'w-1.5 h-1.5 bg-zinc-700 group-hover:bg-zinc-500' : 'w-1.5 h-1.5 bg-zinc-300 group-hover:bg-zinc-400'"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div v-if="!featured.length"
                        class="rounded-2xl border px-6 py-10 text-center"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <span class="mx-auto mb-3 w-11 h-11 rounded-2xl flex items-center justify-center"
                            :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                            <Wifi :size="20" :stroke-width="1.5" />
                        </span>
                        <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                            {{ t('home.no_servers_online') }}
                        </p>
                        <p class="text-[11.5px] mt-1" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('home.no_servers_online_hint') }}
                        </p>
                    </div>
                    </section>

                    <!-- ── Who is here ── -->
                    <div class="hc-reveal rounded-xl border overflow-hidden" style="animation-delay:0.1s"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">

                        <section v-for="(group, gi) in presenceGroups" :key="group.key"
                            :class="gi > 0 ? (dark ? 'border-t border-zinc-800/60' : 'border-t border-zinc-200') : ''"
                            :aria-label="group.label">

                            <div class="flex items-center gap-2 px-3.5 py-2.5 border-b"
                                :class="dark ? 'bg-[#17171a] border-zinc-800/60' : 'bg-zinc-50 border-zinc-200'">
                                <span v-if="group.live" class="hc-live-dot" aria-hidden="true" />
                                <Clock v-else :size="11" :stroke-width="2.2"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'" aria-hidden="true" />
                                <h3 class="text-[10.5px] font-black uppercase tracking-widest flex-1"
                                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ group.label }}</h3>
                                <span v-if="group.data?.users.length"
                                    class="px-2 py-0.5 rounded-full text-[11px] font-bold border tabular-nums"
                                    :class="group.countClass">
                                    {{ group.data.users.length }}
                                </span>
                            </div>

                            <div class="px-3.5 py-3">
                                <!-- Guests and bots: context for the member count above -->
                                <div v-if="group.data?.guests || group.data?.bots || group.hint"
                                    class="flex items-center gap-2 flex-wrap mb-2.5 text-[10.5px]"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    <span v-if="group.data?.guests">{{ t('home.guests_count', { count: group.data.guests }) }}</span>
                                    <span v-if="group.data?.bots">{{ t('home.bots_count', { count: group.data.bots }) }}</span>
                                    <span v-if="group.hint" class="ml-auto">{{ group.hint }}</span>
                                </div>

                                <ul v-if="group.data?.users.length" class="flex flex-wrap gap-1.5">
                                    <li v-for="u in group.data.users" :key="u.id">
                                        <Link :href="u.username ? route('profile.show', { username: u.username }) : '#'"
                                            class="group/user flex items-center gap-1.5 pl-1 pr-2.5 py-1 rounded-full border text-[11.5px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                            :class="dark ? 'border-zinc-800 bg-zinc-900/60 text-zinc-300 hover:text-blue-400 hover:border-zinc-700' : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:text-blue-600 hover:border-zinc-300'"
                                            :title="u.username ? '@' + u.username : u.name">
                                            <span class="w-5 h-5 rounded-full overflow-hidden shrink-0 flex items-center justify-center text-[9px] font-black"
                                                :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-600'">
                                                <!-- alt is empty on purpose: the name is right beside it,
                                                     so announcing the avatar too would just repeat it. -->
                                                <img v-if="u.avatar" :src="u.avatar" alt="" loading="lazy" decoding="async"
                                                    class="w-full h-full object-cover" />
                                                <span v-else aria-hidden="true">{{ initial(u.name) }}</span>
                                            </span>
                                            <span class="truncate max-w-[110px]">{{ u.name }}</span>
                                        </Link>
                                    </li>
                                </ul>
                                <p v-else class="text-[11.5px]" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">
                                    {{ group.empty }}
                                </p>
                            </div>
                        </section>
                    </div>

                    <ExtensionSlot name="home.right.bottom" />
                </div>

            </div>
        </div>

    </PublicLayout>
</template>