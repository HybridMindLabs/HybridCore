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
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref, onMounted, watch } from 'vue';

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
interface HistoryPoint { time: string; players: number | null; max: number }
interface Review {
    id: number; rating: number; body: string | null; created_at: string; is_mine: boolean;
    user: { username: string | null; name: string; avatar: string | null };
}

const props = defineProps<{
    game: Game;
    server: GameServer;
    map_image: string | null;
    history: HistoryPoint[];
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
const textMute = computed(() => dark.value ? 'text-zinc-600' : 'text-zinc-400');
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

function copyIp() {
    navigator.clipboard.writeText(props.server.address);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 1500);
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href);
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 1500);
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
function countryFlag(code: string) {
    return code.toUpperCase().replace(/./g, c => String.fromCodePoint(c.charCodeAt(0) + 127397));
}
function pingLabel(ms: number | null) {
    if (ms === null) return '—';
    return ms + 'ms';
}
function pingColor(ms: number | null) {
    if (ms === null) return dark.value ? 'text-zinc-600' : 'text-zinc-400';
    if (ms < 50)  return 'text-emerald-500';
    if (ms < 100) return 'text-amber-500';
    return 'text-red-500';
}

// Chart
const chartRef = ref<HTMLCanvasElement | null>(null);

onMounted(() => drawChart());
watch(dark, () => drawChart());

function drawChart() {
    const canvas = chartRef.value;
    if (!canvas || !props.history.length) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.offsetWidth;
    const h = canvas.offsetHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    ctx.scale(dpr, dpr);

    const data = props.history.filter(p => p.players !== null) as (HistoryPoint & { players: number })[];
    if (data.length < 2) return;

    const maxVal = Math.max(...data.map(p => p.max), 1);
    const pad = { top: 12, right: 12, bottom: 32, left: 32 };
    const cw = w - pad.left - pad.right;
    const ch = h - pad.top - pad.bottom;
    const accent = props.game.color;
    const grid = dark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)';
    const textC = dark.value ? '#52525b' : '#a1a1aa';

    ctx.clearRect(0, 0, w, h);

    for (let i = 0; i <= 4; i++) {
        const y = pad.top + (ch / 4) * i;
        ctx.strokeStyle = grid; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(w - pad.right, y); ctx.stroke();
        ctx.fillStyle = textC; ctx.font = '10px system-ui'; ctx.textAlign = 'right';
        ctx.fillText(String(Math.round(maxVal * (1 - i / 4))), pad.left - 4, y + 3);
    }

    const xStep = cw / (data.length - 1);
    const gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + ch);
    gradient.addColorStop(0, accent + '38');
    gradient.addColorStop(1, accent + '00');

    ctx.beginPath();
    data.forEach((p, i) => {
        const x = pad.left + i * xStep;
        const y = pad.top + ch - (p.players / maxVal) * ch;
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.lineTo(pad.left + (data.length - 1) * xStep, pad.top + ch);
    ctx.lineTo(pad.left, pad.top + ch);
    ctx.closePath();
    ctx.fillStyle = gradient; ctx.fill();

    ctx.beginPath();
    ctx.strokeStyle = accent; ctx.lineWidth = 2; ctx.lineJoin = 'round';
    data.forEach((p, i) => {
        const x = pad.left + i * xStep;
        const y = pad.top + ch - (p.players / maxVal) * ch;
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();

    ctx.fillStyle = textC; ctx.font = '10px system-ui'; ctx.textAlign = 'center';
    data.forEach((p, i) => {
        if (i % 6 === 0 || i === data.length - 1)
            ctx.fillText(p.time, pad.left + i * xStep, h - 6);
    });
}
</script>

<template>
    <Head :title="server.name" />

    <PublicLayout>

        <!-- ── Hero Banner ── -->
        <div class="relative overflow-hidden" style="min-height: 230px">
            <div v-if="game.cover_url" class="absolute inset-0 overflow-hidden">
                <div
                    class="absolute inset-[-10%] bg-cover bg-center"
                    :style="{ backgroundImage: `url(${game.cover_url})`, filter: 'blur(18px) brightness(0.28) saturate(1.3)' }"
                />
            </div>
            <div v-else class="absolute inset-0" :style="{ background: `linear-gradient(135deg, ${game.color}40, ${game.color}10)` }" />
            <div class="absolute inset-0" style="background: linear-gradient(to right, rgba(9,9,11,0.65) 0%, transparent 65%)" />
            <div
                class="absolute inset-0"
                :style="dark
                    ? 'background: linear-gradient(to bottom, transparent 40%, rgba(9,9,11,1) 100%)'
                    : 'background: linear-gradient(to bottom, transparent 35%, rgba(244,244,245,1) 100%)'"
            />
            <!-- Dot grid (same as Home hero) -->
            <div v-if="dark" class="absolute inset-0 pointer-events-none opacity-50"
                style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />

            <div class="relative z-10 max-w-[1200px] mx-auto px-4 sm:px-6 py-8 pb-0">
                <Breadcrumb :items="[
                    { label: 'Home', href: route('home') },
                    { label: t('servers.title'), href: route('servers.index') },
                    { label: game.name, href: route('servers.game', game.slug) },
                    { label: server.address },
                ]" />

                <!-- Server identity -->
                <div class="flex items-start gap-4 mb-5 flex-wrap">
                    <!-- Game icon -->
                    <div
                        class="w-14 h-14 rounded-xl overflow-hidden border shrink-0 shadow-xl"
                        :style="{ borderColor: game.color + '45' }"
                    >
                        <img
                            :src="`/images/games/icons/64x64/${game.slug}.png`"
                            :alt="game.name"
                            class="w-full h-full object-cover"
                            @error="(e) => { (e.target as HTMLImageElement).style.display = 'none' }"
                        />
                    </div>

                    <div class="flex-1 min-w-0">
                        <!-- Name + status -->
                        <div class="flex items-center gap-3 flex-wrap mb-1.5">
                            <h1
                                class="text-[24px] sm:text-[30px] font-black leading-tight tracking-tight truncate"
                                :class="dark ? 'text-white' : 'text-zinc-900'"
                            >{{ server.name }}</h1>
                            <div class="flex items-center gap-2 shrink-0">
                                <span
                                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1 rounded-full border"
                                    :class="server.status?.is_online
                                        ? 'border-emerald-500/30 bg-emerald-500/12 text-emerald-500'
                                        : dark ? 'border-white/10 bg-white/4 text-white/30' : 'border-zinc-300 bg-zinc-100 text-zinc-400'"
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full"
                                        :class="server.status?.is_online ? 'bg-emerald-500 animate-pulse' : dark ? 'bg-zinc-600' : 'bg-zinc-300'"
                                    />
                                    {{ server.status?.is_online ? t('servers.online') : t('servers.offline') }}
                                </span>
                                <span v-if="average_rating"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border border-amber-500/30 bg-amber-500/12 text-amber-500">
                                    <Star :size="10" :stroke-width="2" fill="currentColor" />
                                    {{ Number(average_rating).toFixed(1) }}
                                </span>
                                <span v-if="server.country_code" class="text-[20px]">{{ countryFlag(server.country_code) }}</span>
                            </div>
                        </div>

                        <!-- Meta row -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="font-mono text-[13px]" :class="dark ? 'text-white/35' : 'text-zinc-400'">{{ server.address }}</span>
                            <span v-if="server.status?.map" class="flex items-center gap-1 text-[12px]" :class="dark ? 'text-white/35' : 'text-zinc-400'">
                                <Map :size="11" :stroke-width="1.8" />{{ server.status.map }}
                            </span>
                            <Lock v-if="server.status?.is_password_protected" :size="11" :stroke-width="2" class="text-amber-400" />
                            <span v-if="server.status?.vac_secured" class="flex items-center gap-1 text-[11px] font-semibold text-emerald-500">
                                <Shield :size="11" :stroke-width="2" />VAC
                            </span>
                            <span
                                v-for="tag in server.tags"
                                :key="tag"
                                class="text-[10px] font-medium px-2 py-0.5 rounded border"
                                :class="dark ? 'border-white/10 text-white/30' : 'border-zinc-200 text-zinc-400'"
                            >{{ tag }}</span>
                        </div>
                    </div>
                </div>

                <!-- Players bar -->
                <div v-if="server.status?.is_online" class="pb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[12px] flex items-center gap-1.5" :class="dark ? 'text-white/35' : 'text-zinc-400'">
                            <Users :size="12" :stroke-width="1.8" />
                            {{ server.status.players_online }} / {{ server.status.players_max }} {{ t('servers.players').toLowerCase() }}
                        </span>
                        <span v-if="server.status.ping != null" class="flex items-center gap-1 text-[12px]" :class="pingColor(server.status.ping)">
                            <Wifi :size="11" :stroke-width="1.8" />{{ pingLabel(server.status.ping) }}
                        </span>
                    </div>
                    <div class="h-1.5 w-full rounded-full overflow-hidden" :class="dark ? 'bg-white/8' : 'bg-zinc-200/70'">
                        <div
                            class="h-full rounded-full transition-all"
                            :style="{
                                width: playerBarWidth(server.status.players_online, server.status.players_max),
                                backgroundColor: playerBarColor(server.status.players_online, server.status.players_max),
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Content ── -->
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-5">

                <!-- ── Left: chart + players ── -->
                <div class="flex flex-col gap-5 min-w-0">

                    <!-- Player history chart -->
                    <div class="rounded-xl border overflow-hidden" :class="card">
                        <div class="flex items-center justify-between px-5 py-3.5 border-b" :class="cardHead">
                            <p class="text-[13px] font-semibold" :class="textPri">{{ t('servers.player_history') }}</p>
                            <span class="text-[11px]" :class="textMute">24h</span>
                        </div>
                        <div class="p-5">
                            <div v-if="history.length >= 2" class="h-52 w-full">
                                <canvas ref="chartRef" class="w-full h-full" />
                            </div>
                            <div v-else class="flex flex-col items-center justify-center h-36">
                                <Activity :size="24" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                                <p class="text-[13px]" :class="textMute">No history data yet</p>
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
                                        :class="i <= Math.round(average_rating) ? 'text-amber-500' : (dark ? 'text-zinc-700' : 'text-zinc-300')" />
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
                                            :class="i <= (hoverRating || reviewForm.rating) ? 'text-amber-500' : (dark ? 'text-zinc-700' : 'text-zinc-300')" />
                                    </button>
                                    <span v-if="reviewForm.rating" class="ml-1.5 text-[12px] font-semibold" :class="textSec">{{ reviewForm.rating }}/5</span>
                                </div>
                                <p v-if="reviewForm.errors.rating" class="text-[12px] text-red-400">{{ reviewForm.errors.rating }}</p>

                                <textarea
                                    v-model="reviewForm.body"
                                    rows="3"
                                    maxlength="1000"
                                    :placeholder="t('servers.review_placeholder')"
                                    class="w-full rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none"
                                    :class="dark
                                        ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
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
                                                    :class="i <= review.rating ? 'text-amber-500' : (dark ? 'text-zinc-700' : 'text-zinc-300')" />
                                            </div>
                                            <span class="text-[11px]" :class="textMute">{{ review.created_at }}</span>
                                            <ReportButton v-if="authed && !review.is_mine" type="review" :id="review.id" class="ml-auto" />
                                            <button
                                                v-if="review.is_mine"
                                                type="button"
                                                class="p-1 rounded transition"
                                                :class="[dark ? 'text-zinc-600 hover:text-red-400' : 'text-zinc-300 hover:text-red-500', (authed && !review.is_mine) ? '' : 'ml-auto']"
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
                                <span class="text-[18px]">{{ countryFlag(server.country_code) }}</span>
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
                                    :class="server.status?.vac_secured ? 'text-emerald-500' : dark ? 'text-zinc-600' : 'text-zinc-400'"
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
                                    :class="server.status?.is_password_protected ? 'text-amber-500' : dark ? 'text-zinc-600' : 'text-zinc-400'"
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
