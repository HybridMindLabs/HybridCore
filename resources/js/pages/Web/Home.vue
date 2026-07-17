<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { Activity, Star, ArrowRight, Users, Server, Gamepad2, Wifi, ChevronLeft, ChevronRight, Newspaper, Eye, Clock } from '@lucide/vue';
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PublicSidebar from '@/components/UI/PublicSidebar.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import GameThumbnail from '@/components/UI/GameThumbnail.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import HomeNewsCard from '@/components/UI/HomeNewsCard.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface GameFilter { id: number; slug: string; name: string }
interface GameStat { slug: string; name: string; players: number; max_players: number }
interface HomeServer {
    id: number; name: string; game_slug: string; game_name: string; game_id: number;
    tags: string[]; is_online: boolean; map: string; map_image: string | null;
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
    playerHistory: number[];
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

function fillPercent(filter: { players: number; max: number }) {
    if (!filter.max) return 0;
    return Math.min(100, Math.round((filter.players / filter.max) * 100));
}
const filtered = computed(() => {
    if (activeFilter.value === 'all') return props.servers;
    if (activeFilter.value === 'mine') return props.servers.filter(s => props.preferredGameSlugs.includes(s.game_slug));
    return props.servers.filter(s => s.game_slug === activeFilter.value);
});

// ── Featured slider ──────────────────────────────────────────────
const featured = computed(() => props.servers.filter(s => s.is_online).slice(0, 6));
const failedMapImages = reactive(new Set<number>());
const slideIndex = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

function nextSlide() { slideIndex.value = (slideIndex.value + 1) % featured.value.length; }
function prevSlide() { slideIndex.value = (slideIndex.value - 1 + featured.value.length) % featured.value.length; }
function goToSlide(i: number) { slideIndex.value = i; resetTimer(); }
function resetTimer() {
    if (timer) clearInterval(timer);
    if (featured.value.length > 1) timer = setInterval(nextSlide, 5000);
}
onMounted(() => resetTimer());
onUnmounted(() => { if (timer) clearInterval(timer); });

const gameColors: Record<string, string> = {
    'cs2': '#f97316', 'csgo': '#f97316', 'css': '#eab308',
    'garrysmod': '#a78bfa', 'tf2': '#ef4444', 'minecraft': '#22c55e',
    'valheim': '#06b6d4', 'rust': '#b45309', 'dod': '#3b82f6',
};
function gameColor(slug: string) { return gameColors[slug] ?? '#3b82f6'; }

function pingColor(ping: number) {
    if (ping < 50)  return dark.value ? 'text-emerald-400' : 'text-emerald-600';
    if (ping < 100) return dark.value ? 'text-amber-400'   : 'text-amber-600';
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
    <Head :title="pageTitle" />

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
        >
            <!-- Glows + dot grid -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-14 sm:py-20">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-10">

                    <!-- Text -->
                    <div class="max-w-2xl">
                        <!-- Live badge -->
                        <div
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                            :class="dark ? 'border-emerald-500/25 bg-emerald-500/8 text-emerald-400' : 'border-emerald-400/30 bg-emerald-50 text-emerald-600'"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                            {{ stats.online_servers }} servers live
                        </div>

                        <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            The
                            <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">
                                {{ page.props.app.name }}
                            </span>
                            <br>Gaming Network
                        </h1>

                        <p class="mt-4 text-[15px] leading-relaxed max-w-lg"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            Browse, connect and manage game servers. Track stats, find players and build your gaming community.
                        </p>

                        <!-- Stats pills -->
                        <div class="flex items-center gap-5 mt-8 flex-wrap">
                            <div v-for="item in [
                                { icon: Gamepad2, value: stats.games,   label: 'Games'   },
                                { icon: Server,   value: stats.servers, label: 'Servers' },
                                { icon: Users,    value: stats.members, label: 'Members' },
                                { icon: Wifi,     value: totalPlayers,  label: 'Players' },
                            ]" :key="item.label" class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                                    :class="dark ? 'bg-zinc-900 border border-zinc-800' : 'bg-white border border-zinc-200 shadow-sm'">
                                    <component :is="item.icon" :size="14" :stroke-width="1.8" class="text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-[16px] font-black leading-none tabular-nums"
                                       :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ item.value.toLocaleString() }}</p>
                                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5"
                                       :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ item.label }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div class="flex items-center gap-3 mt-8 flex-wrap">
                            <Link :href="route('servers.index')"
                                class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl transition shadow-md shadow-blue-500/25">
                                Browse Servers <ArrowRight :size="13" :stroke-width="2.2" />
                            </Link>
                            <Link v-if="!auth" :href="route('register')"
                                class="inline-flex items-center font-bold text-[13px] px-5 py-2.5 rounded-xl border transition"
                                :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-200 text-zinc-700 hover:border-zinc-300 hover:bg-white'">
                                Join for Free
                            </Link>
                        </div>
                    </div>

                    <!-- Game icon grid -->
                    <div class="hidden lg:flex flex-wrap gap-2.5 max-w-[280px] justify-end">
                        <Link
                            v-for="game in games.slice(0, 12)"
                            :key="game.slug"
                            :href="route('servers.game', game.slug)"
                            class="w-12 h-12 rounded-xl overflow-hidden border transition hover:-translate-y-0.5 hover:shadow-md"
                            :class="dark ? 'border-zinc-800/80 bg-zinc-900 hover:border-zinc-700' : 'border-zinc-200 bg-white shadow-sm hover:border-zinc-300'"
                            :title="game.name"
                        >
                            <GameIcon :slug="game.slug" :alt="game.name" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-6">
            <div class="grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)_300px] gap-6">

                <!-- Sidebar -->
                <div class="xl:order-first order-last flex flex-col gap-4">
                    <PublicSidebar :game-stats="gameStats" :total-players="totalPlayers" :max-players="maxPlayers" :player-history="playerHistory" :viewer="viewer" :stats="stats" :community-activity="communityActivity" />
                    <ExtensionSlot name="home.left.bottom" />
                </div>

                <!-- Middle column: News + Servers -->
                <div class="min-w-0 flex flex-col gap-6">
                    <ExtensionSlot name="home.middle.top" />

                    <!-- ══════════════════════════ LATEST NEWS -->
                    <template v-if="latestNews.length">
                        <div>
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-1 h-5 rounded-full bg-blue-500" />
                                    <h2 class="text-[20px] font-black tracking-tight"
                                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('home.latest_news') }}</h2>
                                </div>
                                <Link :href="route('news.index')"
                                    class="hidden sm:flex items-center gap-1.5 text-[12px] transition-colors"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-400 hover:text-zinc-900'">
                                    All articles <ArrowRight :size="12" :stroke-width="1.75" />
                                </Link>
                            </div>

                            <!-- News cards -->
                            <div class="rounded-xl border overflow-hidden divide-y"
                                :class="dark ? 'border-zinc-800/70 bg-[#111113] divide-zinc-800/50' : 'border-zinc-200 bg-white divide-zinc-100 shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                                <Link v-for="a in latestNews.slice(0, 3)" :key="a.id"
                                    :href="route('news.show', a.slug)"
                                    class="group flex gap-4 p-4 transition-colors"
                                    :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50'">

                                    <!-- Thumbnail -->
                                    <div class="relative shrink-0 w-[110px] h-[76px] rounded-lg overflow-hidden"
                                        :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
                                        <img v-if="a.featured_image_url" :src="a.featured_image_url"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                            :class="dark ? 'opacity-80 group-hover:opacity-100' : ''" />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <Newspaper :size="18" :stroke-width="1.3"
                                                :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                                        </div>
                                        <!-- Category color strip -->
                                        <div v-if="a.category" class="absolute bottom-0 left-0 right-0 h-0.5"
                                            :style="{ background: a.category.color }" />
                                    </div>

                                    <!-- Text -->
                                    <div class="min-w-0 flex flex-col gap-1.5 flex-1">
                                        <div v-if="a.category" class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: a.category.color }" />
                                            <span class="text-[11px] font-bold uppercase tracking-wide" :style="{ color: a.category.color }">
                                                {{ a.category.name }}
                                            </span>
                                        </div>

                                        <h3 class="text-[14px] font-bold leading-snug line-clamp-2 transition-colors"
                                            :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">
                                            {{ a.title }}
                                        </h3>

                                        <p v-if="a.excerpt" class="text-[12px] leading-relaxed line-clamp-1"
                                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                            {{ a.excerpt }}
                                        </p>

                                        <!-- Meta row -->
                                        <div class="flex items-center gap-3 mt-auto text-[11px]"
                                            :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                            <span v-if="a.author" class="font-medium"
                                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                                {{ a.author.name }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Eye :size="10" :stroke-width="1.8" />{{ a.views }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <Clock :size="10" :stroke-width="1.8" />{{ a.reading_time }}m
                                            </span>
                                            <span class="ml-auto">{{ a.published_at }}</span>
                                        </div>
                                    </div>
                                </Link>
                            </div>

                            <!-- See more button -->
                            <div class="mt-3 flex">
                                <Link :href="route('news.index')"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-[13px] font-semibold transition-colors"
                                    :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-200 text-zinc-600 hover:text-zinc-900 hover:border-zinc-300 hover:bg-white'">
                                    <Newspaper :size="13" :stroke-width="1.8" />
                                    {{ t('home.see_more_articles') }}
                                    <ArrowRight :size="12" :stroke-width="2" />
                                </Link>
                            </div>
                        </div>
                    </template>
                    <!-- ══════════════════════ END LATEST NEWS -->

                    <!-- ══════════════════════════ SERVERS -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-1 h-5 rounded-full bg-emerald-500" />
                                <div>
                                    <h2 class="text-[20px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                        {{ t('home.servers_title') }}
                                    </h2>
                                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                                        {{ stats.online_servers }} online · {{ totalPlayers.toLocaleString() }}/{{ maxPlayers.toLocaleString() }} players
                                    </p>
                                </div>
                            </div>
                            <Link :href="route('servers.index')" class="hidden sm:flex items-center gap-1.5 text-[12px] transition-colors"
                                :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-400 hover:text-zinc-900'">
                                Browse all <ArrowRight :size="12" :stroke-width="1.75" />
                            </Link>
                        </div>

                        <!-- Game filters (chip doubles as a capacity progress bar) -->
                        <div class="flex items-center gap-1 flex-wrap">
                            <button v-for="filter in gameFilters" :key="filter.key" type="button"
                                class="relative overflow-hidden flex flex-col px-3 pt-1.5 pb-2 text-[12px] rounded-lg border transition-colors font-medium"
                                :class="activeFilter === filter.key
                                    ? dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-400/40 bg-blue-50 text-blue-600'
                                    : dark ? 'border-zinc-800/80 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700 hover:bg-white/[0.04]'
                                           : 'border-zinc-200 bg-white text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'"
                                :title="`${filter.players.toLocaleString()} / ${filter.max.toLocaleString()} players (${fillPercent(filter)}% full)`"
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
                            <div class="hidden lg:grid grid-cols-[16px_40px_minmax(0,1fr)_90px_100px_70px_80px] items-center gap-3 px-4 py-2.5 text-[11px] uppercase tracking-wider font-semibold border-b"
                                :class="dark ? 'bg-[#1a1a1e] text-zinc-600 border-zinc-800/60' : 'bg-zinc-50 text-zinc-400 border-zinc-100'">
                                <span/><span/><span>Server</span>
                                <span>{{ t('home.col_map') }}</span>
                                <span>{{ t('home.col_players') }}</span>
                                <span>Ping</span><span/>
                            </div>

                            <div v-for="server in filtered" :key="server.id"
                                class="grid grid-cols-[16px_40px_minmax(0,1fr)] lg:grid-cols-[16px_40px_minmax(0,1fr)_90px_100px_70px_80px] items-center gap-3 px-4 py-3 border-b last:border-0 transition-colors"
                                :class="dark ? 'border-zinc-800/50 hover:bg-white/[0.03]' : 'border-zinc-100 hover:bg-zinc-50'">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="server.is_online ? 'bg-emerald-500' : dark ? 'bg-zinc-700' : 'bg-zinc-300'" />
                                <GameThumbnail :game="server.game_slug" size="w-8 h-8 rounded-md" />
                                <div class="min-w-0">
                                    <Link :href="server.show_route" class="block text-[14px] font-medium truncate transition-colors"
                                        :class="dark ? 'text-zinc-100 hover:text-blue-400' : 'text-zinc-800 hover:text-blue-600'">{{ server.name }}</Link>
                                    <p class="text-[12px] truncate" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                        {{ server.game_name }}<template v-if="server.tags.length"> · {{ server.tags[0] }}</template>
                                    </p>
                                </div>
                                <div class="hidden lg:block text-[13px] truncate" :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ server.map }}</div>
                                <div class="hidden lg:block text-[13px] font-mono" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                                    {{ server.players }}<span :class="dark ? 'text-zinc-700' : 'text-zinc-300'">/</span>{{ server.max_players }}
                                </div>
                                <div class="hidden lg:block text-[13px] font-mono" :class="pingColor(server.ping)">{{ server.ping }}ms</div>
                                <div class="col-span-3 lg:col-span-1 flex items-center justify-end gap-1">
                                    <button type="button"
                                        class="w-7 h-7 flex items-center justify-center rounded-md border transition-colors"
                                        :class="[dark ? 'border-zinc-800/80 hover:border-zinc-600 hover:bg-white/[0.05]' : 'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50',
                                                 server.is_favourited ? 'text-amber-400' : dark ? 'text-zinc-600 hover:text-zinc-300' : 'text-zinc-400 hover:text-zinc-600']"
                                        @click="toggleFavourite(server)">
                                        <Star :size="13" :stroke-width="1.75" :fill="server.is_favourited ? 'currentColor' : 'none'" />
                                    </button>
                                    <a :href="server.connect_route"
                                        class="w-7 h-7 flex items-center justify-center rounded-md border transition-colors"
                                        :class="dark ? 'border-zinc-800/80 text-zinc-600 hover:border-blue-500/50 hover:text-blue-400 hover:bg-blue-500/5' : 'border-zinc-200 text-zinc-400 hover:border-blue-300 hover:text-blue-500 hover:bg-blue-50'">
                                        <ArrowRight :size="13" :stroke-width="1.75" />
                                    </a>
                                </div>
                            </div>

                            <div v-if="!filtered.length" class="flex flex-col items-center justify-center py-16 text-center">
                                <Activity :size="24" :stroke-width="1.5" class="mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                                <p class="text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('home.no_servers') }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- ══════════════════════ END SERVERS -->
                    <ExtensionSlot name="home.middle.bottom" />
                </div>

                <!-- ── Right column ── -->
                <div class="hidden xl:flex flex-col gap-3">
                    <ExtensionSlot name="home.right.top" />

                    <div class="flex items-center justify-between mb-1">
                        <h3 class="text-[11px] font-black uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                            {{ t('home.featured_servers') }}
                        </h3>
                        <div v-if="featured.length > 1" class="flex items-center gap-1">
                            <button type="button" @click="() => { prevSlide(); resetTimer(); }"
                                class="w-6 h-6 flex items-center justify-center rounded-md border transition-colors"
                                :class="dark ? 'border-zinc-800 text-zinc-600 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-400 hover:text-zinc-700'">
                                <ChevronLeft :size="12" :stroke-width="2.2" />
                            </button>
                            <button type="button" @click="() => { nextSlide(); resetTimer(); }"
                                class="w-6 h-6 flex items-center justify-center rounded-md border transition-colors"
                                :class="dark ? 'border-zinc-800 text-zinc-600 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-400 hover:text-zinc-700'">
                                <ChevronRight :size="12" :stroke-width="2.2" />
                            </button>
                        </div>
                    </div>

                    <!-- Cards -->
                    <div class="relative" style="min-height: 340px">
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
                                                    {{ server.is_online ? 'Online' : 'Offline' }}
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
                                                <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">Map</span>
                                                <span class="text-[12px] font-mono truncate max-w-[160px]" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ server.map }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-[10px] font-bold uppercase tracking-widest shrink-0" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">Players</span>
                                                <div class="flex items-center gap-2 flex-1 justify-end">
                                                    <div class="h-1 flex-1 max-w-[80px] rounded-full overflow-hidden" :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'">
                                                        <div class="h-full rounded-full"
                                                            :style="`width:${server.max_players > 0 ? Math.round((server.players / server.max_players) * 100) : 0}%; background:${gameColor(server.game_slug)}`" />
                                                    </div>
                                                    <span class="text-[12px] font-mono shrink-0" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">{{ server.players }}/{{ server.max_players }}</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">Ping</span>
                                                <span class="text-[12px] font-mono" :class="pingColor(server.ping)">{{ server.ping }}ms</span>
                                            </div>
                                        </div>

                                        <!-- Connect -->
                                        <a :href="server.connect_route"
                                            class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-bold border transition-colors"
                                            :style="`color: ${gameColor(server.game_slug)}; border-color: ${gameColor(server.game_slug)}30; background: ${gameColor(server.game_slug)}12`"
                                        >
                                            Connect <ArrowRight :size="13" :stroke-width="2.2" />
                                        </a>
                                    </div>
                                </div>
                            </Transition>
                        </template>
                    </div>

                    <!-- Dot indicators -->
                    <div v-if="featured.length > 1" class="flex items-center justify-center gap-1.5 pt-1">
                        <button v-for="(_, i) in featured" :key="i" type="button"
                            class="rounded-full transition-all duration-300"
                            :class="i === slideIndex
                                ? 'w-5 h-1.5 bg-blue-500'
                                : dark ? 'w-1.5 h-1.5 bg-zinc-700 hover:bg-zinc-500' : 'w-1.5 h-1.5 bg-zinc-300 hover:bg-zinc-400'"
                            @click="goToSlide(i)"
                        />
                    </div>

                    <div v-if="!featured.length"
                        class="rounded-2xl border p-10 text-center"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <Wifi :size="22" :stroke-width="1.5" class="mx-auto mb-2" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                        <p class="text-[12px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('home.no_servers_online') }}</p>
                    </div>

                    <Link :href="route('servers.index')"
                        class="flex items-center justify-center gap-2 py-2.5 rounded-xl border text-[12px] font-semibold transition"
                        :class="dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-400 hover:text-zinc-700 hover:border-zinc-300'">
                        {{ t('home.view_all') }} <ArrowRight :size="12" :stroke-width="2" />
                    </Link>

                    <!-- ── Кой е онлайн / Активни 24ч ── -->
                    <div class="rounded-xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">

                        <!-- Секция: онлайн сега -->
                        <div class="flex items-center gap-2 px-3 py-2.5 border-b"
                            :class="dark ? 'bg-[#1a1a1e] border-zinc-800/60' : 'bg-zinc-50 border-zinc-100'">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0" />
                            <span class="text-[10px] font-black uppercase tracking-widest flex-1"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('home.who_is_online') }}</span>
                        </div>
                        <div class="px-3 pt-2.5 pb-2 flex flex-wrap gap-1.5">
                            <span v-if="whoIsOnline?.users.length" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border border-emerald-500/30 bg-emerald-500/10 text-emerald-400">
                                {{ whoIsOnline!.users.length }} {{ t('home.sidebar_online').toLowerCase() }}
                            </span>
                            <span v-if="whoIsOnline?.guests" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border"
                                :class="dark ? 'border-zinc-700/60 bg-zinc-800/60 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                {{ t('home.guests_count', { count: whoIsOnline!.guests }) }}
                            </span>
                            <span v-if="whoIsOnline?.bots" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border"
                                :class="dark ? 'border-zinc-800 bg-zinc-900 text-zinc-600' : 'border-zinc-200 bg-zinc-50 text-zinc-400'">
                                {{ t('home.bots_count', { count: whoIsOnline!.bots }) }}
                            </span>
                            <span class="text-[10px] w-full mt-0.5" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ t('home.last_5_minutes') }}</span>
                        </div>
                        <div class="px-3 pb-3 flex flex-wrap gap-1.5">
                            <template v-if="whoIsOnline?.users.length">
                                <Link v-for="u in whoIsOnline!.users" :key="u.id"
                                    :href="u.username ? route('profile.show', { username: u.username }) : '#'"
                                    class="flex items-center gap-1.5 px-2 py-1 rounded-lg border text-[11px] font-semibold transition-colors"
                                    :class="dark ? 'border-zinc-800/70 bg-zinc-900/60 text-zinc-300 hover:text-blue-400 hover:border-zinc-700' : 'border-zinc-200 bg-white text-zinc-700 hover:text-blue-600 shadow-sm'">
                                    <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 flex items-center justify-center text-[9px] font-black"
                                        :class="dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-200 text-zinc-500'">
                                        <img v-if="u.avatar" :src="u.avatar" class="w-full h-full object-cover" />
                                        <span v-else>{{ u.name[0].toUpperCase() }}</span>
                                    </div>
                                    {{ u.name }}
                                </Link>
                            </template>
                            <p v-else class="text-[11px]" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ t('home.no_registered_online') }}</p>
                        </div>

                        <!-- Divider -->
                        <div class="border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'" />

                        <!-- Секция: активни 24ч -->
                        <div class="flex items-center gap-2 px-3 py-2.5 border-b"
                            :class="dark ? 'bg-[#1a1a1e] border-zinc-800/60' : 'bg-zinc-50 border-zinc-100'">
                            <Clock :size="10" :stroke-width="2.2" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" />
                            <span class="text-[10px] font-black uppercase tracking-widest flex-1"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('home.active_last_24h') }}</span>
                        </div>
                        <div class="px-3 pt-2.5 pb-2 flex flex-wrap gap-1.5">
                            <span v-if="activeToday?.users.length" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border border-blue-500/30 bg-blue-500/10 text-blue-400">
                                {{ t('home.users_count', { count: activeToday!.users.length }) }}
                            </span>
                            <span v-if="activeToday?.guests" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border"
                                :class="dark ? 'border-zinc-700/60 bg-zinc-800/60 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                {{ t('home.guests_count', { count: activeToday!.guests }) }}
                            </span>
                            <span v-if="activeToday?.bots" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border"
                                :class="dark ? 'border-zinc-800 bg-zinc-900 text-zinc-600' : 'border-zinc-200 bg-zinc-50 text-zinc-400'">
                                {{ t('home.bots_count', { count: activeToday!.bots }) }}
                            </span>
                        </div>
                        <div class="px-3 pb-3 flex flex-wrap gap-1.5">
                            <template v-if="activeToday?.users.length">
                                <Link v-for="u in activeToday!.users" :key="u.id"
                                    :href="u.username ? route('profile.show', { username: u.username }) : '#'"
                                    class="flex items-center gap-1.5 px-2 py-1 rounded-lg border text-[11px] font-semibold transition-colors"
                                    :class="dark ? 'border-zinc-800/70 bg-zinc-900/60 text-zinc-300 hover:text-blue-400 hover:border-zinc-700' : 'border-zinc-200 bg-white text-zinc-700 hover:text-blue-600 shadow-sm'">
                                    <div class="w-5 h-5 rounded-full overflow-hidden shrink-0 flex items-center justify-center text-[9px] font-black"
                                        :class="dark ? 'bg-zinc-800 text-zinc-500' : 'bg-zinc-200 text-zinc-500'">
                                        <img v-if="u.avatar" :src="u.avatar" class="w-full h-full object-cover" />
                                        <span v-else>{{ u.name[0].toUpperCase() }}</span>
                                    </div>
                                    {{ u.name }}
                                </Link>
                            </template>
                            <p v-else class="text-[11px]" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">{{ t('home.no_active_users') }}</p>
                        </div>
                    </div>

                    <ExtensionSlot name="home.right.bottom" />
                </div>

            </div>
        </div>

    </PublicLayout>
</template>