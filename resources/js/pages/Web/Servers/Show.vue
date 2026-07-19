<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import {
    Lock, Shield, Wifi, Copy, Check, Heart, MousePointerClick,
    Users, Activity, Zap, TrendingUp, Clock, Map, Globe2,
    ExternalLink, Tag, Info, CircleCheck, CircleX, Share2,
    Star, Trash2, MessageSquare,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import ReportButton from '@/components/UI/ReportButton.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import PlayerAreaChart from '@/components/UI/PlayerAreaChart.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

interface PlayerStatus {
    is_online: boolean; map: string | null; players_online: number; players_max: number;
    ping: number | null; is_password_protected: boolean; vac_secured: boolean;
    game_version: string | null; players: { name: string; score: number; duration: string }[];
}
interface GameServer {
    id: number; ip: string; port: number; address: string; name: string;
    country_code: string | null; tags: string[]; is_favourited: boolean;
    status: PlayerStatus | null;
}
interface Game {
    id: number; name: string; slug: string; icon: string; color: string;
    cover_url?: string | null;
}
interface HistoryRangeData {
    history: { t: string; players: number | null }[];
    peak: number;
    average: number;
    uptime: number;
    samples: number;
}
interface Review {
    id: number; rating: number; body: string | null; created_at: string; is_mine: boolean;
    user: { username: string | null; name: string; avatar: string | null };
}

const props = defineProps<{
    game: Game;
    server: GameServer;
    map_image: string | null;
    history_ranges: Record<'24h' | '7d' | '30d', HistoryRangeData>;
    stats: {
        total_clicks: number; clicks_today: number; peak_players: number;
        uptime_24h: number; uptime_7d: number | null; uptime_30d: number | null;
    };
    uptime_daily: { date: string; pct: number | null }[];
    reviews: { data: Review[]; total: number };
    user_review: { rating: number; body: string | null } | null;
    average_rating: number | null;
}>();

function uptimeBarColor(pct: number | null): string {
    if (pct === null) return dark.value ? '#27272a' : '#e4e4e7';
    if (pct >= 99) return '#22c55e';
    if (pct >= 90) return '#f59e0b';
    return '#ef4444';
}

function uptimeTooltip(day: { date: string; pct: number | null }): string {
    const d = new Date(day.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    return day.pct === null
        ? `${d} — ${t('servers.uptime_no_data')}`
        : `${d} — ${t('servers.uptime_online_pct', { pct: day.pct })}`;
}

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

// Color tokens
const card     = computed(() => dark.value ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]');
const cardHead = computed(() => dark.value ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50');
const textPri  = computed(() => dark.value ? 'text-zinc-100' : 'text-zinc-800');
const textSec  = computed(() => dark.value ? 'text-zinc-400' : 'text-zinc-500');
const textMute = computed(() => dark.value ? 'text-zinc-500' : 'text-zinc-400');
const divider  = computed(() => dark.value ? 'divide-zinc-800/50' : 'divide-zinc-100');
const rowHover = computed(() => dark.value ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50/80');
const infoRow  = computed(() => dark.value ? 'border-zinc-800/50' : 'border-zinc-100');

const copied = ref(false);
const linkCopied = ref(false);
const isFavourited = ref(props.server.is_favourited);
const favouriteLoading = ref(false);
const mapImageFailed = ref(false);

// ── Reviews ────────────────────────────────────────────────────────────────
const page = usePage<{ auth: { user: { name: string } | null } }>();
const authed = computed(() => !!page.props.auth?.user);

const reviewForm = useForm({
    rating: props.user_review?.rating ?? 0,
    body: props.user_review?.body ?? '',
});
const hoverRating = ref(0);
const showReviewForm = ref(false);

function submitReview() {
    if (!reviewForm.rating) return;
    reviewForm.post(route('servers.reviews.store', props.server.id), {
        preserveScroll: true,
        onSuccess: () => { showReviewForm.value = false; },
    });
}

function deleteReview(reviewId: number) {
    router.delete(route('servers.reviews.destroy', { server: props.server.id, review: reviewId }), {
        preserveScroll: true,
    });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

/**
 * The Clipboard API needs a secure context. Over plain http it throws, and the
 * old version still flipped the button to "copied" without copying anything.
 */
async function writeClipboard(text: string) {
    try {
        await navigator.clipboard.writeText(text);
        return;
    } catch {
        const field = document.createElement('textarea');
        field.value = text;
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
}

async function copyIp() {
    await writeClipboard(props.server.address);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 1600);
}

async function copyLink() {
    await writeClipboard(window.location.href);
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 1600);
}

async function toggleFavourite() {
    const previous = isFavourited.value;
    isFavourited.value = !previous; // optimistic — rolled back on failure
    favouriteLoading.value = true;
    try {
        const res = await fetch(route('servers.favourite', props.server.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        });
        if (!res.ok) throw new Error('request failed');
        const data = await res.json();
        isFavourited.value = data.favourited;
    } catch {
        isFavourited.value = previous;
    } finally { favouriteLoading.value = false; }
}

function playerBarWidth(online: number, max: number) {
    if (!max) return '0%';
    return `${Math.min(100, Math.round((online / max) * 100))}%`;
}
function playerBarColor(online: number, max: number) {
    if (!max) return '#52525b';
    const pct = online / max;
    if (pct >= 0.9) return '#ef4444';
    if (pct >= 0.6) return '#f59e0b';
    return '#22c55e';
}
function pingLabel(ms: number | null) {
    if (ms === null) return '—';
    return ms + 'ms';
}
/**
 * One token per theme. Measured on the light card, the previous single set was
 * unreadable: emerald-500 2.54:1, amber-500 2.15:1, red-500 3.76:1, against
 * the 4.5:1 this size of text needs.
 */
function pingColor(ms: number | null) {
    if (ms === null) return dark.value ? 'text-zinc-400' : 'text-zinc-500';
    if (ms < 50) return dark.value ? 'text-emerald-500' : 'text-emerald-800';
    if (ms < 100) return dark.value ? 'text-amber-500' : 'text-amber-700';

    return dark.value ? 'text-red-400' : 'text-red-600';
}

/**
 * The old canvas chart dropped every null before plotting, so an hour with no
 * snapshot silently became a straight line between two distant readings — it
 * claimed data that was never collected. Nulls are passed through instead and
 * PlayerAreaChart breaks the line across them.
 *
 * It also never redrew on resize and had no hover readout; the shared component
 * covers both.
 */
type HistoryRange = '24h' | '7d' | '30d';

const historyRange = ref<HistoryRange>('24h');

const historyTabs = computed<{ key: HistoryRange; label: string }[]>(() => [
    { key: '24h', label: t('servers.range_24h') },
    { key: '7d', label: t('servers.range_7d') },
    { key: '30d', label: t('servers.range_30d') },
]);

const activeHistory = computed(() => props.history_ranges[historyRange.value]);

const chartData = computed(() => activeHistory.value.history);

const historyTiles = computed(() => [
    {
        label: t('servers.average_players'),
        value: activeHistory.value.average.toLocaleString(),
        suffix: '',
        hint: t('servers.average_players_hint'),
    },
    {
        label: t('servers.peak_players'),
        value: activeHistory.value.peak.toLocaleString(),
        suffix: '',
        hint: t('servers.peak_players_hint'),
    },
    {
        label: t('servers.uptime_label'),
        value: String(activeHistory.value.uptime),
        suffix: '%',
        hint: t('servers.uptime_label_hint'),
    },
]);

const heroStats = computed(() => {
    const status = props.server.status;
    const capacity = status && status.players_max
        ? Math.min(100, Math.round((status.players_online / status.players_max) * 100))
        : 0;

    return [
        {
            label: t('servers.players'),
            value: status?.is_online ? `${status.players_online}/${status.players_max}` : '—',
            suffix: '',
            hint: t('servers.hero_players_now_hint'),
            class: undefined as string | undefined,
            bar: status?.is_online ? capacity : undefined,
            barColor: status ? playerBarColor(status.players_online, status.players_max) : '#71717a',
        },
        {
            label: t('servers.col_ping'),
            value: pingLabel(status?.ping ?? null),
            suffix: '',
            hint: t('servers.hero_ping_hint'),
            class: pingColor(status?.ping ?? null),
            bar: undefined,
            barColor: '',
        },
        {
            label: t('servers.uptime_label'),
            value: String(props.stats.uptime_24h),
            suffix: '%',
            hint: t('servers.hero_uptime_hint'),
            class: undefined as string | undefined,
            bar: undefined,
            barColor: '',
        },
    ];
});
</script>

<template>
    <Head>
        <title>{{ server.name }}</title>
        <meta name="description" :content="t('servers.meta_show', { name: server.name, game: game.name })" />
    </Head>

    <PublicLayout>

        <!-- ── Hero Banner ── -->
        <div class="relative overflow-hidden" style="min-height: 230px">
            <div v-if="game.cover_url" class="absolute inset-0 overflow-hidden">
                <div
                    class="absolute inset-[-10%] bg-cover bg-center"
                    :style="{ backgroundImage: `url(${game.cover_url})`, filter: dark ? 'blur(18px) brightness(0.42) saturate(1.2)' : 'blur(18px) brightness(1.06) saturate(1.1)' }"
                />
            </div>
            <div v-else class="absolute inset-0" :style="{ background: `linear-gradient(135deg, ${game.color}40, ${game.color}10)` }" />
            <!-- This side wash was hard-coded to near-black in both themes, so in
                 light mode the near-black heading sat on a dark scrim. Each theme
                 now scrims with its own page colour. -->
            <div class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to right, rgba(9,9,11,0.88) 0%, rgba(9,9,11,0.70) 45%, transparent 85%)'
                    : 'background: linear-gradient(to right, rgba(236,238,242,0.95) 0%, rgba(236,238,242,0.82) 45%, rgba(236,238,242,0.30) 85%)'" />
            <div
                class="absolute inset-0" aria-hidden="true"
                :style="dark
                    ? 'background: linear-gradient(to bottom, transparent 40%, rgba(9,9,11,1) 100%)'
                    : 'background: linear-gradient(to bottom, transparent 35%, rgba(236,238,242,1) 100%)'"
            />
            <div class="absolute inset-0 pointer-events-none" aria-hidden="true"
                :class="dark ? 'opacity-50' : 'opacity-[0.3]'"
                :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />

            <div class="relative z-10 max-w-[1200px] mx-auto px-4 sm:px-6 py-8 pb-0">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    { label: t('servers.title'), href: route('servers.index') },
                    { label: game.name, href: route('servers.game', game.slug) },
                    { label: server.address },
                ]" />

                <div class="grid gap-6 lg:gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,320px)] lg:items-end mt-4 pb-6">

                    <div class="min-w-0">
                        <!-- Status. aria-live so a screen reader hears it change. -->
                        <div class="hc-hero-in inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[10.5px] font-bold uppercase tracking-widest"
                            :class="server.status?.is_online
                                ? (dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800')
                                : (dark ? 'border-zinc-700 bg-zinc-800/60 text-zinc-400' : 'border-zinc-300 bg-zinc-200/70 text-zinc-500')"
                            aria-live="polite">
                            <span v-if="server.status?.is_online" class="hc-live-dot" aria-hidden="true" />
                            {{ server.status?.is_online ? t('servers.online') : t('servers.offline') }}
                        </div>

                        <div class="hc-hero-in hc-hero-in--1 flex items-start gap-4 mt-4">
                            <Link :href="route('servers.game', game.slug)"
                                class="w-14 h-14 rounded-xl overflow-hidden ring-2 shrink-0 shadow-xl transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-blue-500"
                                :class="dark ? 'ring-zinc-800' : 'ring-white'"
                                :title="t('servers.game_servers_title', { game: game.name })">
                                <GameIcon :slug="game.slug" :alt="game.name" />
                            </Link>

                            <div class="min-w-0">
                                <h1 class="text-[24px] sm:text-[30px] font-black leading-tight tracking-tight"
                                    :class="dark ? 'text-white' : 'text-zinc-900'">{{ server.name }}</h1>

                                <div class="flex items-center gap-2 flex-wrap mt-2">
                                    <Link :href="route('servers.game', game.slug)"
                                        class="text-[12.5px] font-semibold transition-colors rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                        :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                                        {{ game.name }}
                                    </Link>
                                    <span v-if="average_rating"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full border border-amber-500/30 bg-amber-500/10"
                                        :class="dark ? 'text-amber-400' : 'text-amber-700'"
                                        :title="t('servers.rating_hint', { count: reviews.total })">
                                        <Star :size="10" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                                        {{ Number(average_rating).toFixed(1) }}
                                    </span>
                                    <span v-if="server.country_code"
                                        class="px-2 py-0.5 rounded-full border text-[10px] font-bold tracking-wide"
                                        :class="dark ? 'border-zinc-700 bg-zinc-800/70 text-zinc-300' : 'border-zinc-300 bg-zinc-200/70 text-zinc-500'"
                                        :title="server.country_code.toUpperCase()">{{ server.country_code.toUpperCase() }}</span>
                                    <span v-if="server.status?.is_password_protected"
                                        class="inline-flex items-center gap-1 text-[10.5px] font-bold px-2 py-0.5 rounded-full border border-amber-500/30 bg-amber-500/10"
                                        :class="dark ? 'text-amber-400' : 'text-amber-700'"
                                        :title="t('servers.password_protected')">
                                        <Lock :size="9" :stroke-width="2.4" aria-hidden="true" />
                                        {{ t('servers.password_protected') }}
                                    </span>
                                    <span v-if="server.status?.vac_secured"
                                        class="inline-flex items-center gap-1 text-[10.5px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/30 bg-emerald-500/10"
                                        :class="dark ? 'text-emerald-400' : 'text-emerald-800'"
                                        :title="t('servers.vac_secured')">
                                        <Shield :size="9" :stroke-width="2.4" aria-hidden="true" />VAC
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- The address is the thing people came for; it used to be
                             one faint line of meta text. -->
                        <div class="hc-hero-in hc-hero-in--2 flex items-center gap-2 mt-4 flex-wrap">
                            <div class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 rounded-xl border backdrop-blur-md"
                                :class="dark ? 'border-zinc-700/70 bg-zinc-900/80' : 'border-zinc-300 bg-white'">
                                <span class="font-mono text-[13.5px] font-semibold"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ server.address }}</span>
                                <button type="button" @click="copyIp"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="copied
                                        ? 'text-emerald-500'
                                        : dark ? 'text-zinc-500 hover:text-zinc-100 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/70'"
                                    :aria-label="t('home.copy_address', { address: server.address })"
                                    :title="copied ? t('home.copied') : t('home.copy_address', { address: server.address })">
                                    <component :is="copied ? Check : Copy" :size="14" :stroke-width="2" aria-hidden="true" />
                                </button>
                            </div>

                            <span v-if="server.status?.map"
                                class="inline-flex items-center gap-1.5 text-[12.5px] font-mono px-2.5 py-1.5 rounded-xl border"
                                :class="dark ? 'border-zinc-800 bg-zinc-900/60 text-zinc-300' : 'border-zinc-300 bg-white/80 text-zinc-500'"
                                :title="t('servers.current_map_hint')">
                                <Map :size="12" :stroke-width="1.8" aria-hidden="true" />{{ server.status.map }}
                            </span>
                        </div>

                        <div v-if="server.tags.length" class="hc-hero-in hc-hero-in--3 flex items-center gap-1.5 flex-wrap mt-2.5">
                            <span v-for="tag in server.tags" :key="tag"
                                class="text-[10px] font-semibold px-2 py-0.5 rounded"
                                :class="dark ? 'bg-zinc-800/80 text-zinc-400' : 'bg-zinc-200/80 text-zinc-500'"
                            >{{ tag }}</span>
                        </div>
                    </div>

                    <!-- Every figure explains itself, and the player count keeps
                         its capacity bar. -->
                    <dl class="hc-hero-in hc-hero-in--2 grid grid-cols-3 gap-2.5">
                        <div v-for="(item, i) in heroStats" :key="item.label"
                            class="hc-reveal flex flex-col rounded-xl border px-3 py-2.5 backdrop-blur-md"
                            :style="{ animationDelay: 0.18 + i * 0.06 + 's' }"
                            :class="dark
                                ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                                : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'"
                            :title="item.hint">
                            <!-- Term first, as a <dl> group requires; `order`
                                 puts the figure back above its label. The bar and
                                 the hint are descriptions too, so they stay <dd>
                                 rather than becoming stray elements in the group. -->
                            <dt class="order-2 text-[10px] font-bold uppercase tracking-widest mt-1.5"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.label }}</dt>
                            <dd class="order-1 text-[19px] font-black leading-none tabular-nums"
                                :class="item.class ?? (dark ? 'text-zinc-100' : 'text-zinc-900')">
                                {{ item.value }}<span v-if="item.suffix" class="text-[12px] font-bold"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.suffix }}</span>
                            </dd>

                            <dd v-if="item.bar !== undefined" class="order-3 mt-2 h-1 rounded-full overflow-hidden"
                                :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'" aria-hidden="true">
                                <span class="hc-hero-bar block h-full rounded-full"
                                    :style="{ width: item.bar + '%', backgroundColor: item.barColor }" />
                            </dd>
                            <dd v-else class="order-3 text-[10.5px] leading-snug mt-1"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ item.hint }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- ── Content ── -->
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-5">

                <!-- ── Left: chart + players ── -->
                <div class="flex flex-col gap-5 min-w-0">

                    <!-- Player history -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b" :class="cardHead">
                            <p class="text-[13px] font-semibold" :class="textPri">{{ t('servers.player_history') }}</p>
                            <p class="text-[11.5px] mt-0.5" :class="textMute">{{ t('servers.player_history_hint') }}</p>
                        </div>

                        <div class="flex items-center gap-0.5 px-3 border-b overflow-x-auto"
                            :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'"
                            role="group" :aria-label="t('servers.range_group')">
                            <button v-for="tab in historyTabs" :key="tab.key" type="button"
                                class="px-4 py-2.5 text-[12.5px] font-semibold border-b-2 -mb-px whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                                :class="historyRange === tab.key
                                    ? 'text-blue-500 border-blue-500'
                                    : dark ? 'text-zinc-500 border-transparent hover:text-zinc-200' : 'text-zinc-500 border-transparent hover:text-zinc-900'"
                                :aria-pressed="historyRange === tab.key"
                                @click="historyRange = tab.key">
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="px-5 pt-5 pb-3">
                            <PlayerAreaChart
                                :data="chartData"
                                :dark="dark"
                                :height="208"
                                :label="t('servers.player_history')"
                                :empty-label="t('servers.not_enough_data')"
                            />
                        </div>

                        <div class="grid grid-cols-3 gap-px border-t"
                            :class="dark ? 'bg-zinc-800/60 border-zinc-800/60' : 'bg-zinc-200 border-zinc-200'">
                            <div v-for="tile in historyTiles" :key="tile.label"
                                class="px-4 py-3 text-center" :class="dark ? 'bg-[#111113]' : 'bg-white'"
                                :title="tile.hint">
                                <p class="text-[11px] font-semibold" :class="textSec">{{ tile.label }}</p>
                                <p class="text-[20px] font-black leading-none tabular-nums mt-1.5" :class="textPri">
                                    {{ tile.value }}<span v-if="tile.suffix" class="text-[12px] font-bold" :class="textMute">{{ tile.suffix }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Current players -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b flex items-center justify-between" :class="cardHead">
                            <p class="text-[13px] font-semibold" :class="textPri">{{ t('servers.current_players') }}</p>
                            <span v-if="server.status?.is_online" class="text-[12px]" :class="textMute">
                                {{ server.status.players.length }} / {{ server.status.players_max }}
                            </span>
                        </div>

                        <template v-if="server.status?.is_online && server.status.players.length">
                            <!-- Column headers -->
                            <div
                                class="grid grid-cols-[minmax(0,1fr)_70px_80px] px-5 py-2.5 border-b text-[10px] font-semibold uppercase tracking-wider"
                                :class="[infoRow, textMute]"
                            >
                                <span>{{ t('servers.player_name') }}</span>
                                <span class="text-right">{{ t('servers.player_score') }}</span>
                                <span class="text-right">{{ t('servers.player_time') }}</span>
                            </div>
                            <!-- Rows -->
                            <div class="divide-y" :class="divider">
                                <div
                                    v-for="(player, idx) in server.status.players"
                                    :key="player.name + idx"
                                    class="grid grid-cols-[minmax(0,1fr)_70px_80px] px-5 py-3 items-center transition-colors"
                                    :class="rowHover"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div
                                            class="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-bold shrink-0 uppercase select-none"
                                            :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-100 text-zinc-500'"
                                        >{{ (player.name || '?')[0] }}</div>
                                        <p class="text-[13px] font-medium truncate" :class="textPri">{{ player.name || '—' }}</p>
                                    </div>
                                    <span class="text-[12px] font-semibold text-right tabular-nums" :class="textSec">{{ player.score }}</span>
                                    <span class="text-[11px] text-right flex items-center justify-end gap-1" :class="textMute">
                                        <Clock :size="10" :stroke-width="1.8" />{{ player.duration }}
                                    </span>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex flex-col items-center justify-center px-5 py-12 text-center">
                            <Users :size="24" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                            <p class="text-[14px]" :class="textMute">
                                {{ server.status?.is_online ? t('servers.no_players') : t('servers.offline') }}
                            </p>
                        </div>
                    </div>

                    <!-- ── Reviews ── -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b flex items-center justify-between gap-3" :class="cardHead">
                            <div class="flex items-center gap-2">
                                <MessageSquare :size="13" :stroke-width="1.8" :class="textMute" />
                                <p class="text-[13px] font-semibold" :class="textPri">{{ t('servers.reviews') }}</p>
                                <span class="text-[11px]" :class="textMute">({{ reviews.total }})</span>
                            </div>
                            <div v-if="average_rating" class="flex items-center gap-1.5">
                                <div class="flex items-center gap-0.5">
                                    <Star v-for="i in 5" :key="i" :size="13" :stroke-width="1.8"
                                        :fill="i <= Math.round(average_rating) ? '#f59e0b' : 'none'"
                                        :class="i <= Math.round(average_rating) ? 'text-amber-500' : (dark ? 'text-zinc-500' : 'text-zinc-300')" />
                                </div>
                                <span class="text-[13px] font-bold tabular-nums" :class="textPri">{{ Number(average_rating).toFixed(1) }}</span>
                            </div>
                        </div>

                        <!-- Write / edit review -->
                        <div v-if="authed" class="px-5 py-4 border-b" :class="infoRow">
                            <button
                                v-if="!showReviewForm"
                                type="button"
                                class="flex items-center gap-2 text-[13px] font-semibold transition-colors"
                                :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
                                @click="showReviewForm = true"
                            >
                                <Star :size="13" :stroke-width="2" />
                                {{ user_review ? t('servers.edit_review') : t('servers.write_review') }}
                            </button>

                            <form v-else class="flex flex-col gap-3" @submit.prevent="submitReview">
                                <!-- Star picker -->
                                <div class="flex items-center gap-1" @mouseleave="hoverRating = 0">
                                    <button
                                        v-for="i in 5" :key="i" type="button"
                                        class="p-0.5 transition-transform hover:scale-110"
                                        @mouseenter="hoverRating = i"
                                        @click="reviewForm.rating = i"
                                    >
                                        <Star :size="20" :stroke-width="1.8"
                                            :fill="i <= (hoverRating || reviewForm.rating) ? '#f59e0b' : 'none'"
                                            :class="i <= (hoverRating || reviewForm.rating) ? 'text-amber-500' : (dark ? 'text-zinc-500' : 'text-zinc-300')" />
                                    </button>
                                    <span v-if="reviewForm.rating" class="ml-1.5 text-[12px] font-semibold" :class="textSec">{{ reviewForm.rating }}/5</span>
                                </div>
                                <p v-if="reviewForm.errors.rating" class="text-[12px] text-red-400">{{ reviewForm.errors.rating }}</p>

                                <label for="review_body" class="sr-only">{{ t('servers.review_label') }}</label>
                                <textarea
                                    id="review_body"
                                    v-model="reviewForm.body"
                                    rows="3"
                                    maxlength="1000"
                                    :placeholder="t('servers.review_placeholder')"
                                    class="w-full rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none"
                                    :class="dark
                                        ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-500 focus:border-blue-500/50'
                                        : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'"
                                />
                                <p v-if="reviewForm.errors.body" class="text-[12px] text-red-400">{{ reviewForm.errors.body }}</p>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="submit"
                                        :disabled="reviewForm.processing || !reviewForm.rating"
                                        class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-[12px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ reviewForm.processing ? t('servers.saving') : (user_review ? t('servers.update_review') : t('servers.post_review')) }}
                                    </button>
                                    <button
                                        type="button"
                                        class="px-4 py-2 rounded-lg border text-[12px] font-medium transition"
                                        :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800'"
                                        @click="showReviewForm = false"
                                    >{{ t('servers.cancel') }}</button>
                                </div>
                            </form>
                        </div>
                        <div v-else class="px-5 py-4 border-b" :class="infoRow">
                            <p class="text-[13px]" :class="textMute">
                                <Link :href="route('login')" class="font-semibold" :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'">{{ t('servers.login') }}</Link>
                                {{ t('servers.review_login_suffix') }}
                            </p>
                        </div>

                        <!-- Review list -->
                        <div v-if="reviews.data.length" class="divide-y" :class="divider">
                            <div v-for="review in reviews.data" :key="review.id" class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <component
                                        :is="review.user.username ? Link : 'div'"
                                        :href="review.user.username ? route('profile.show', review.user.username) : undefined"
                                        class="w-9 h-9 rounded-xl overflow-hidden shrink-0"
                                    >
                                        <img v-if="review.user.avatar" :src="review.user.avatar" class="w-full h-full object-cover" :alt="review.user.name" />
                                        <div v-else class="w-full h-full flex items-center justify-center text-[13px] font-black text-white uppercase"
                                            :style="{ backgroundColor: avatarBg(review.user.name) }">{{ review.user.name[0] }}</div>
                                    </component>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <component
                                                :is="review.user.username ? Link : 'span'"
                                                :href="review.user.username ? route('profile.show', review.user.username) : undefined"
                                                class="text-[13px] font-bold truncate"
                                                :class="[textPri, review.user.username ? (dark ? 'hover:text-blue-400' : 'hover:text-blue-600') : '']"
                                            >{{ review.user.name }}</component>
                                            <div class="flex items-center gap-0.5">
                                                <Star v-for="i in 5" :key="i" :size="11" :stroke-width="1.8"
                                                    :fill="i <= review.rating ? '#f59e0b' : 'none'"
                                                    :class="i <= review.rating ? 'text-amber-500' : (dark ? 'text-zinc-500' : 'text-zinc-300')" />
                                            </div>
                                            <span class="text-[11px]" :class="textMute">{{ review.created_at }}</span>
                                            <ReportButton v-if="authed && !review.is_mine" type="review" :id="review.id" class="ml-auto" />
                                            <button
                                                v-if="review.is_mine"
                                                type="button"
                                                class="p-1 rounded transition"
                                                :class="[dark ? 'text-zinc-500 hover:text-red-400' : 'text-zinc-300 hover:text-red-500', (authed && !review.is_mine) ? '' : 'ml-auto']"
                                                :title="t('servers.delete_review')"
                                                @click="deleteReview(review.id)"
                                            >
                                                <Trash2 :size="12" :stroke-width="2" />
                                            </button>
                                        </div>
                                        <p v-if="review.body" class="text-[13px] leading-relaxed mt-1" :class="textSec">{{ review.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center px-5 py-10 text-center">
                            <Star :size="22" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                            <p class="text-[13px]" :class="textMute">{{ t('servers.no_reviews') }}</p>
                        </div>
                    </div>
                </div>

                <!-- ── Right sidebar ── -->
                <div class="flex flex-col gap-4">

                    <ExtensionSlot name="server.show.sidebar" :context="{ serverId: server.id }" />

                    <!-- Connect card -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b" :class="cardHead">
                            <p class="text-[13px] font-semibold" :class="textPri">{{ t('servers.connect') }}</p>
                        </div>
                        <div class="p-4 flex flex-col gap-2.5">
                            <a
                                :href="route('servers.connect', { game: game.slug, ip: server.ip, port: server.port })"
                                class="flex items-center justify-center gap-2 w-full py-3 rounded-xl text-[14px] font-bold text-white transition hover:opacity-90 active:scale-[0.98]"
                                :style="{ backgroundColor: game.color }"
                            >
                                <ExternalLink :size="15" :stroke-width="2" />
                                {{ t('servers.connect') }}
                            </a>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-medium border transition"
                                :class="dark
                                    ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 hover:bg-white/[0.04]'
                                    : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300 hover:bg-zinc-50'"
                                @click="copyIp"
                            >
                                <component :is="copied ? Check : Copy" :size="14" :stroke-width="1.8" :class="copied ? 'text-emerald-500' : ''" />
                                <span :class="copied ? 'text-emerald-500' : ''">{{ copied ? t('servers.copied') : t('servers.copy_ip') }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-medium border transition"
                                :class="isFavourited
                                    ? 'border-red-500/30 bg-red-500/10 text-red-400'
                                    : dark
                                        ? 'border-zinc-800/70 text-zinc-400 hover:text-red-400 hover:border-red-500/25 hover:bg-red-500/5'
                                        : 'border-zinc-200 text-zinc-500 hover:text-red-400 hover:border-red-300 hover:bg-red-50/50'"
                                :disabled="favouriteLoading"
                                @click="toggleFavourite"
                            >
                                <Heart :size="14" :stroke-width="1.8" :fill="isFavourited ? 'currentColor' : 'none'" />
                                {{ isFavourited ? t('servers.unfavourite') : t('servers.favourite') }}
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-medium border transition"
                                :class="dark
                                    ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 hover:bg-white/[0.04]'
                                    : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300 hover:bg-zinc-50'"
                                @click="copyLink"
                            >
                                <component :is="linkCopied ? Check : Share2" :size="14" :stroke-width="1.8" :class="linkCopied ? 'text-emerald-500' : ''" />
                                <span :class="linkCopied ? 'text-emerald-500' : ''">{{ linkCopied ? 'Link copied!' : 'Copy page link' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Server details -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b flex items-center gap-2" :class="cardHead">
                            <Info :size="13" :stroke-width="1.8" :class="textMute" />
                            <p class="text-[13px] font-semibold" :class="textPri">Server Details</p>
                        </div>

                        <!-- Current map preview -->
                        <div v-if="map_image && !mapImageFailed" class="relative border-b" :class="infoRow">
                            <img :src="map_image" :alt="server.status?.map ?? ''" class="w-full h-28 object-cover"
                                @error="mapImageFailed = true" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                            <p class="absolute bottom-2 left-4 text-[12px] font-mono font-semibold text-white flex items-center gap-1.5">
                                <Map :size="11" :stroke-width="2" />{{ server.status?.map }}
                            </p>
                        </div>

                        <div class="divide-y" :class="divider">
                            <div class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px]" :class="textMute">Game</span>
                                <span class="text-[13px] font-medium" :class="textSec">{{ game.name }}</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px]" :class="textMute">Address</span>
                                <span class="font-mono text-[12px]" :class="textSec">{{ server.address }}</span>
                            </div>
                            <div v-if="server.status?.map" class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px] flex items-center gap-1.5" :class="textMute">
                                    <Map :size="12" :stroke-width="1.8" />Map
                                </span>
                                <span class="text-[13px] font-medium" :class="textSec">{{ server.status.map }}</span>
                            </div>
                            <div v-if="server.country_code" class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px] flex items-center gap-1.5" :class="textMute">
                                    <Globe2 :size="12" :stroke-width="1.8" />Country
                                </span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide"
                                    :class="dark ? 'bg-zinc-800 text-zinc-300' : 'bg-zinc-200 text-zinc-500'">{{ server.country_code.toUpperCase() }}</span>
                            </div>
                            <div v-if="server.status?.ping != null" class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px] flex items-center gap-1.5" :class="textMute">
                                    <Wifi :size="12" :stroke-width="1.8" />Ping
                                </span>
                                <span class="text-[13px] font-semibold tabular-nums" :class="pingColor(server.status.ping)">
                                    {{ pingLabel(server.status.ping) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px] flex items-center gap-1.5" :class="textMute">
                                    <Shield :size="12" :stroke-width="1.8" />VAC
                                </span>
                                <span
                                    class="flex items-center gap-1 text-[12px] font-medium"
                                    :class="server.status?.vac_secured ? 'text-emerald-500' : dark ? 'text-zinc-500' : 'text-zinc-400'"
                                >
                                    <component :is="server.status?.vac_secured ? CircleCheck : CircleX" :size="13" :stroke-width="1.8" />
                                    {{ server.status?.vac_secured ? 'Secured' : 'Disabled' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px] flex items-center gap-1.5" :class="textMute">
                                    <Lock :size="12" :stroke-width="1.8" />Password
                                </span>
                                <span
                                    class="flex items-center gap-1 text-[12px] font-medium"
                                    :class="server.status?.is_password_protected ? 'text-amber-500' : dark ? 'text-zinc-500' : 'text-zinc-400'"
                                >
                                    <component :is="server.status?.is_password_protected ? Lock : CircleCheck" :size="13" :stroke-width="1.8" />
                                    {{ server.status?.is_password_protected ? 'Required' : 'None' }}
                                </span>
                            </div>
                            <div v-if="server.status?.game_version" class="flex items-center justify-between px-5 py-3">
                                <span class="text-[12px]" :class="textMute">Version</span>
                                <span class="text-[12px] font-mono" :class="textSec">{{ server.status.game_version }}</span>
                            </div>
                            <div v-if="server.tags.length" class="flex items-start justify-between px-5 py-3 gap-3">
                                <span class="text-[12px] flex items-center gap-1.5 shrink-0 pt-0.5" :class="textMute">
                                    <Tag :size="12" :stroke-width="1.8" />Tags
                                </span>
                                <div class="flex flex-wrap gap-1 justify-end">
                                    <span
                                        v-for="tag in server.tags"
                                        :key="tag"
                                        class="text-[10px] font-medium px-2 py-0.5 rounded border"
                                        :class="dark ? 'border-zinc-800 text-zinc-500' : 'border-zinc-200 text-zinc-400'"
                                    >{{ tag }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b flex items-center gap-2" :class="cardHead">
                            <Activity :size="13" :stroke-width="1.8" :class="textMute" />
                            <p class="text-[13px] font-semibold" :class="textPri">Statistics</p>
                        </div>
                        <div
                            class="grid grid-cols-2"
                            :class="dark ? '[&>*]:border-zinc-800/50' : '[&>*]:border-zinc-100'"
                        >
                            <div
                                v-for="(stat, i) in [
                                    { icon: MousePointerClick, label: t('servers.total_clicks'),  value: stats.total_clicks,     color: '#818cf8' },
                                    { icon: TrendingUp,        label: t('servers.peak_players'),  value: stats.peak_players,     color: '#22c55e' },
                                    { icon: Zap,               label: t('servers.clicks_today'),  value: stats.clicks_today,     color: '#fb923c' },
                                    { icon: Activity,          label: t('servers.uptime'),         value: stats.uptime_24h + '%', color: '#38bdf8' },
                                ]"
                                :key="stat.label"
                                class="px-5 py-4 border-b border-r last:border-r-0"
                                :class="[i >= 2 ? 'border-b-0' : '', i % 2 === 1 ? 'border-r-0' : '']"
                            >
                                <component :is="stat.icon" :size="14" :stroke-width="1.8" class="mb-2.5" :style="{ color: stat.color }" />
                                <p class="text-[20px] font-bold leading-none tabular-nums mb-1" :class="textPri">{{ stat.value }}</p>
                                <p class="text-[10px] uppercase tracking-wide" :class="textMute">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Uptime (last 30 days) -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="px-5 py-3.5 border-b flex items-center justify-between" :class="cardHead">
                            <div class="flex items-center gap-2">
                                <TrendingUp :size="13" :stroke-width="1.8" :class="textMute" />
                                <p class="text-[13px] font-semibold" :class="textPri">{{ t('servers.uptime_title') }}</p>
                            </div>
                            <span class="text-[11px]" :class="textMute">{{ t('servers.uptime_window') }}</span>
                        </div>
                        <div class="p-4">
                            <!-- Day bars -->
                            <div class="flex items-end gap-[2px] h-8">
                                <div
                                    v-for="day in uptime_daily"
                                    :key="day.date"
                                    class="flex-1 rounded-sm transition-opacity hover:opacity-70"
                                    :style="{
                                        backgroundColor: uptimeBarColor(day.pct),
                                        height: day.pct === null ? '30%' : Math.max(15, day.pct) + '%',
                                    }"
                                    :title="uptimeTooltip(day)"
                                />
                            </div>
                            <div class="flex items-center justify-between mt-1.5 text-[10px]" :class="textMute">
                                <span>{{ t('servers.uptime_ago') }}</span>
                                <span>{{ t('servers.uptime_today') }}</span>
                            </div>

                            <!-- Summary -->
                            <div class="grid grid-cols-3 mt-4 pt-3 border-t text-center" :class="infoRow">
                                <div v-for="w in [
                                    { label: '24h', value: stats.uptime_24h },
                                    { label: '7d',  value: stats.uptime_7d },
                                    { label: '30d', value: stats.uptime_30d },
                                ]" :key="w.label">
                                    <p class="text-[15px] font-bold tabular-nums leading-none"
                                        :style="{ color: w.value === null ? undefined : uptimeBarColor(w.value) }"
                                        :class="w.value === null ? textMute : ''">
                                        {{ w.value === null ? '—' : w.value + '%' }}
                                    </p>
                                    <p class="text-[10px] uppercase tracking-wide mt-1" :class="textMute">{{ w.label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <ExtensionSlot name="server.show.tabs" :context="{ serverId: server.id }" />
        </div>

    </PublicLayout>
</template>
