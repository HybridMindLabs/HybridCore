<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Gamepad2, Users, Server, Wifi, Search, X, ChevronRight } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { useCountUp } from '@/composables/useCountUp';
import { computed, ref, watch } from 'vue';

interface Game {
    id: number; name: string; slug: string; icon: string; color: string;
    cover_url: string | null;
    servers_count: number; players_online: number; online_servers: number;
}

const props = defineProps<{
    games: Game[];
    totals: { games: number; servers: number; players: number; online_servers: number };
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const search = ref('');
const filtered = computed(() => {
    if (!search.value.trim()) return props.games;
    const q = search.value.toLowerCase();
    return props.games.filter(g => g.name.toLowerCase().includes(q));
});

function onlinePercent(g: Game) {
    if (!g.servers_count) return 0;
    return Math.round((g.online_servers / g.servers_count) * 100);
}

// Populated games lead; the rest collapse into a compact row below.
const activeGames = computed(() => filtered.value.filter(g => g.servers_count > 0));
const emptyGames = computed(() => filtered.value.filter(g => g.servers_count === 0));

// Open by default only when there is nothing else to look at.
const showEmptyGames = ref(false);
watch(activeGames, list => { showEmptyGames.value = list.length === 0; }, { immediate: true });

// ── Hero ─────────────────────────────────────────────────────────
const animatedGames = useCountUp(computed(() => props.totals.games));
const animatedServers = useCountUp(computed(() => props.totals.servers));
const animatedOnline = useCountUp(computed(() => props.totals.online_servers));
const animatedPlayers = useCountUp(computed(() => props.totals.players));

const heroTotals = computed(() => [
    { icon: Gamepad2, value: animatedGames.value,   label: t('servers.games'),         hint: t('servers.games_hint') },
    { icon: Server,   value: animatedServers.value, label: t('servers.servers_label'), hint: t('servers.servers_hint') },
    { icon: Wifi,     value: animatedOnline.value,  label: t('servers.online_now'),    hint: t('servers.online_now_hint') },
    { icon: Users,    value: animatedPlayers.value, label: t('servers.players_label'), hint: t('servers.players_hint') },
]);
</script>

<template>
    <Head :title="t('servers.title')" />

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <section class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
            :aria-label="t('servers.title')">

            <!-- Cover mosaic. Decorative: it sits under an opaque scrim and the
                 same games are listed below with their own images. -->
            <div class="absolute inset-0 grid grid-cols-4" aria-hidden="true"
                :class="dark ? 'opacity-100' : 'opacity-[0.55]'">
                <div
                    v-for="game in games.slice(0, 8)"
                    :key="game.id"
                    class="bg-cover bg-center"
                    :style="game.cover_url
                        ? { backgroundImage: `url(${game.cover_url})` }
                        : { backgroundColor: game.color }"
                />
            </div>

            <!-- Two scrims instead of one. A single even wash forces a choice
                 between readable text and visible art: strong enough for the
                 copy and the covers disappear, weak enough for the covers and
                 the copy sits on busy artwork.
                 The vertical layer only blends the hero into the page below; the
                 horizontal layer is near-opaque under the text on the left and
                 fades out to the right, where there is nothing to read. -->
            <div class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to bottom, rgba(9,9,11,0.35) 0%, rgba(9,9,11,0.55) 60%, rgba(9,9,11,0.95) 100%)'
                    : 'background: linear-gradient(to bottom, rgba(236,238,242,0.30) 0%, rgba(236,238,242,0.55) 60%, rgba(236,238,242,0.97) 100%)'" />
            <div class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to right, rgba(9,9,11,0.94) 0%, rgba(9,9,11,0.88) 40%, rgba(9,9,11,0.45) 68%, rgba(9,9,11,0.20) 100%)'
                    : 'background: linear-gradient(to right, rgba(236,238,242,0.96) 0%, rgba(236,238,242,0.92) 40%, rgba(236,238,242,0.55) 68%, rgba(236,238,242,0.30) 100%)'" />

            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <div class="hc-hero-glow absolute -top-32 left-1/4 w-[520px] h-[380px] rounded-full blur-[120px]"
                    :class="dark ? 'bg-blue-500/8' : 'bg-blue-400/10'" />
                <div class="hc-hero-glow hc-hero-glow--slow absolute -top-16 right-1/4 w-[320px] h-[300px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/6' : 'bg-violet-400/8'" />
                <div class="absolute inset-0" :class="dark ? 'opacity-50' : 'opacity-[0.35]'"
                    :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 sm:py-12">
                <Breadcrumb :items="[{ label: t('navigation.nav_home'), href: route('home') }, { label: t('servers.title') }]" />

                <div class="grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)] lg:items-end mt-4">

                    <div class="max-w-xl">
                        <div class="hc-hero-in inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700'"
                            aria-live="polite">
                            <span class="hc-live-dot" aria-hidden="true" />
                            {{ t('servers.hero_badge') }}
                        </div>

                        <h1 class="hc-hero-in hc-hero-in--1 mt-4 text-[30px] sm:text-[40px] font-black leading-[1.07] tracking-tight"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('servers.hero_title_1') }}
                            <span class="hc-hero-gradient bg-clip-text text-transparent">{{ t('servers.hero_title_2') }}</span>
                        </h1>

                        <p class="hc-hero-in hc-hero-in--2 mt-3 text-[15px] leading-relaxed max-w-lg"
                           :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                            {{ t('servers.hero_subtitle', { count: totals.games }) }}
                        </p>

                        <!-- Search: the main tool on this page, so it gets a real
                             label, a clear button and a spoken result count. -->
                        <div class="hc-hero-in hc-hero-in--3 mt-6 w-full max-w-md">
                            <label for="game-search" class="sr-only">{{ t('servers.search_game') }}</label>
                            <div class="relative">
                                <Search :size="16" :stroke-width="1.9" aria-hidden="true"
                                    class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'" />
                                <input
                                    id="game-search"
                                    v-model="search"
                                    type="search"
                                    autocomplete="off"
                                    :placeholder="t('servers.search_game')"
                                    class="w-full pl-10 pr-10 py-3 rounded-xl border text-[14px] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                                    :class="dark
                                        ? 'bg-zinc-900/70 border-zinc-800 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                                        : 'bg-white border-zinc-300 text-zinc-900 placeholder:text-zinc-500 focus:border-blue-500/60'"
                                />
                                <button v-if="search" type="button" @click="search = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200/70'"
                                    :aria-label="t('servers.clear_search')" :title="t('servers.clear_search')">
                                    <X :size="15" :stroke-width="2" aria-hidden="true" />
                                </button>
                            </div>
                            <p class="sr-only" role="status" aria-live="polite">
                                {{ t('servers.results_count', { count: filtered.length }) }}
                            </p>
                        </div>
                    </div>

                    <!-- Totals -->
                    <dl class="hc-hero-in hc-hero-in--2 grid grid-cols-2 gap-2.5">
                        <div v-for="(item, i) in heroTotals" :key="item.label"
                            class="hc-reveal rounded-xl border px-3.5 py-3 backdrop-blur-md"
                            :style="{ animationDelay: 0.2 + i * 0.06 + 's' }"
                            :class="dark
                                ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                                : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'"
                            :title="item.hint">
                            <component :is="item.icon" :size="14" :stroke-width="1.9"
                                class="text-blue-500 mb-1.5" aria-hidden="true" />
                            <dd class="text-[20px] font-black leading-none tabular-nums"
                                :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                {{ item.value.toLocaleString() }}
                            </dd>
                            <dt class="text-[10px] font-bold uppercase tracking-widest mt-1.5"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.label }}</dt>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <!-- ── Games grid ── -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">

            <div class="flex items-end justify-between gap-4 mb-5">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-1 h-9 rounded-full bg-blue-500 shrink-0 mt-0.5" aria-hidden="true" />
                    <div class="min-w-0">
                        <h2 class="text-[20px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ t('servers.browse_by_game') }}
                        </h2>
                        <p class="text-[12.5px] mt-0.5 leading-snug" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('servers.browse_hint') }}
                        </p>
                    </div>
                </div>
                <p class="text-[12.5px] font-semibold shrink-0 tabular-nums"
                   :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('servers.games_count', { count: filtered.length }) }}
                </p>
            </div>

            <!-- Games that actually have servers. Previously every game rendered
                 as a full card, so a site with 2 populated games out of 17 gave
                 the player fifteen empty cards to scroll past first. -->
            <div v-if="activeGames.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link
                    v-for="(game, i) in activeGames"
                    :key="game.id"
                    :href="route('servers.game', game.slug)"
                    class="hc-reveal group relative rounded-2xl border overflow-hidden block transition-all duration-200 hover:-translate-y-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :style="{ animationDelay: Math.min(i, 11) * 0.035 + 's' }"
                    :class="[
                        dark
                            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700 hover:shadow-xl hover:shadow-black/40'
                            : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-[0_10px_28px_rgba(0,0,0,0.10)] hover:border-zinc-300',
                        game.servers_count ? '' : 'opacity-70 hover:opacity-100',
                    ]"
                >
                    <!-- Cover. The old version blurred to 2px and dropped brightness
                         to 0.45, which turned every cover into the same brown smear.
                         A light blur plus a bottom fade keeps the art recognisable
                         while the title below stays readable. -->
                    <div class="relative h-[132px] overflow-hidden">
                        <div
                            v-if="game.cover_url"
                            class="absolute inset-[-4px] bg-cover bg-center transition-transform duration-500 group-hover:scale-[1.06]"
                            :style="{
                                backgroundImage: `url(${game.cover_url})`,
                                filter: dark ? 'blur(1px) brightness(0.78)' : 'blur(1px) brightness(1.02)',
                            }"
                        />
                        <div
                            v-else
                            class="absolute inset-0"
                            :style="{ background: `linear-gradient(135deg, ${game.color}55, ${game.color}15)` }"
                        />

                        <div class="absolute inset-0" aria-hidden="true"
                            :style="dark
                                ? 'background: linear-gradient(to bottom, rgba(17,17,19,0.15) 0%, rgba(17,17,19,0.55) 55%, rgba(17,17,19,1) 100%)'
                                : 'background: linear-gradient(to bottom, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.62) 55%, rgba(255,255,255,1) 100%)'" />

                        <!-- Status pill -->
                        <div v-if="game.online_servers > 0"
                            class="absolute top-3 right-3 flex items-center gap-1.5 pl-2 pr-2.5 py-1 rounded-full backdrop-blur-md border border-emerald-400/30 bg-emerald-500/20">
                            <span class="hc-live-dot" aria-hidden="true" />
                            <span class="text-[10px] font-bold"
                                :class="dark ? 'text-emerald-300' : 'text-emerald-800'">
                                {{ t('servers.online_count', { count: game.online_servers }) }}
                            </span>
                        </div>

                        <!-- Game icon -->
                        <div class="absolute bottom-3 left-4 w-11 h-11 rounded-xl overflow-hidden ring-2 shadow-lg transition-transform duration-300 group-hover:scale-105"
                            :class="dark ? 'ring-zinc-800' : 'ring-white'">
                            <GameIcon :slug="game.slug" :alt="game.name" />
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="px-4 pb-4 pt-3">
                        <h3 class="text-[15px] font-black truncate transition-colors"
                            :class="dark ? 'text-zinc-100 group-hover:text-blue-300' : 'text-zinc-900 group-hover:text-blue-700'">
                            {{ game.name }}
                        </h3>

                        <template v-if="game.servers_count">
                            <div class="flex items-baseline gap-4 mt-2.5">
                                <p class="text-[13px]" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                                    <span class="text-[17px] font-black tabular-nums align-baseline"
                                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ game.servers_count }}</span>
                                    <span class="ml-1.5">{{ t('servers.servers_label').toLowerCase() }}</span>
                                </p>
                                <p class="text-[13px]" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                                    <span class="text-[17px] font-black tabular-nums align-baseline"
                                        :class="game.players_online > 0
                                            ? 'text-emerald-500'
                                            : dark ? 'text-zinc-600' : 'text-zinc-400'">{{ game.players_online }}</span>
                                    <span class="ml-1.5">{{ t('servers.players_label').toLowerCase() }}</span>
                                </p>
                            </div>

                            <!-- "100% online" said nothing on its own; the real
                                 question is how many of the servers are up. -->
                            <div class="mt-3">
                                <div class="flex items-center justify-between mb-1.5 text-[11px]"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    <span>{{ t('servers.online_ratio', { online: game.online_servers, total: game.servers_count }) }}</span>
                                    <span class="font-bold tabular-nums">{{ onlinePercent(game) }}%</span>
                                </div>
                                <div class="w-full h-1.5 rounded-full overflow-hidden"
                                    :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'"
                                    role="progressbar" :aria-valuenow="onlinePercent(game)"
                                    aria-valuemin="0" aria-valuemax="100"
                                    :aria-label="t('servers.online_ratio', { online: game.online_servers, total: game.servers_count })">
                                    <div class="h-full rounded-full transition-all duration-700"
                                        :style="{ width: onlinePercent(game) + '%', backgroundColor: game.color }" />
                                </div>
                            </div>
                        </template>

                        <p v-else class="text-[12.5px] mt-2.5" :class="dark ? 'text-zinc-600' : 'text-zinc-500'">
                            {{ t('servers.no_servers_yet') }}
                        </p>
                    </div>
                </Link>
            </div>

            <!-- Games with nothing hosted yet. Still worth listing — they say
                 what the network supports — but as a compact row rather than a
                 screenful of cards showing zeroes. -->
            <div v-if="emptyGames.length" class="mt-8">
                <button type="button" @click="showEmptyGames = !showEmptyGames"
                    class="group flex items-center gap-2 text-[12.5px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded px-1 -mx-1"
                    :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-600 hover:text-zinc-900'"
                    :aria-expanded="showEmptyGames" aria-controls="empty-games">
                    <ChevronRight :size="13" :stroke-width="2.4" aria-hidden="true"
                        class="transition-transform" :class="showEmptyGames ? 'rotate-90' : ''" />
                    {{ t('servers.other_games', { count: emptyGames.length }) }}
                </button>

                <div v-show="showEmptyGames" id="empty-games"
                    class="flex flex-wrap gap-2 mt-3">
                    <Link v-for="game in emptyGames" :key="game.id"
                        :href="route('servers.game', game.slug)"
                        class="group flex items-center gap-2 pl-1.5 pr-3 py-1.5 rounded-xl border text-[12.5px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark
                            ? 'border-zinc-800 bg-[#111113] text-zinc-400 hover:text-zinc-100 hover:border-zinc-700'
                            : 'border-zinc-200 bg-white text-zinc-600 hover:text-zinc-900 hover:border-zinc-300'"
                        :title="t('servers.no_servers_yet')">
                        <span class="w-6 h-6 rounded-lg overflow-hidden shrink-0 opacity-70 transition-opacity group-hover:opacity-100">
                            <GameIcon :slug="game.slug" :alt="game.name" />
                        </span>
                        {{ game.name }}
                    </Link>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!filtered.length" class="flex flex-col items-center text-center px-6 py-20">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-700' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <Gamepad2 :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                    {{ t('servers.no_games') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('servers.no_games_hint') }}
                </p>
                <button v-if="search" type="button" @click="search = ''"
                    class="mt-5 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border text-[13px] font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="dark ? 'border-zinc-800 text-zinc-300 hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-white'">
                    {{ t('servers.clear_search') }}
                </button>
            </div>
        </div>

    </PublicLayout>
</template>
