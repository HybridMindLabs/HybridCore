<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Activity, Check, CircleCheck, CircleX, Copy, ExternalLink, Globe2, Heart, Info, Lock, Map,
    MousePointerClick, Share2, Shield, Tag, TrendingUp, Wifi, Zap,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { csrfHeaders } from '@/lib/csrf';
import { useClipboard } from '@/composables/useClipboard';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import ServerPanel from './ServerPanel.vue';
import { pingClass, type GameInfo, type ServerStatus } from './types';

/** Connect card, server details, counters and the 30-day uptime strip. */
const props = defineProps<{
    game: GameInfo;
    server: {
        id: number; ip: string; port: number; address: string; country_code: string | null;
        tags: string[]; is_favourited: boolean;
        status: (ServerStatus & { game_version: string | null }) | null;
    };
    mapImage: string | null;
    stats: {
        total_clicks: number; clicks_today: number; peak_players: number;
        uptime_24h: number; uptime_7d: number | null; uptime_30d: number | null;
    };
    uptimeDaily: { date: string; pct: number | null }[];
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ auth: { user: unknown } | null }>();

const ip = useClipboard();
const pageLink = useClipboard();

// ── Favourite ────────────────────────────────────────────────────
const favourited = ref(props.server.is_favourited);
const favouriteBusy = ref(false);

async function toggleFavourite() {
    if (!page.props.auth?.user) {
        router.visit(route('login'));
        return;
    }

    const previous = favourited.value;
    favourited.value = !previous; // optimistic — rolled back on failure
    favouriteBusy.value = true;
    try {
        const res = await fetch(route('servers.favourite', props.server.id), {
            method: 'POST',
            headers: { ...csrfHeaders(), Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('request failed');
        favourited.value = !!(await res.json()).favourited;
    } catch {
        favourited.value = previous;
    } finally {
        favouriteBusy.value = false;
    }
}

// ── Details ──────────────────────────────────────────────────────
const mapImageFailed = ref(false);

const counters = computed(() => [
    { icon: MousePointerClick, label: t('servers.total_clicks'), value: props.stats.total_clicks, color: '#818cf8' },
    { icon: TrendingUp, label: t('servers.peak_players'), value: props.stats.peak_players, color: '#22c55e' },
    { icon: Zap, label: t('servers.clicks_today'), value: props.stats.clicks_today, color: '#fb923c' },
    { icon: Activity, label: t('servers.uptime'), value: props.stats.uptime_24h + '%', color: '#38bdf8' },
]);

// ── Uptime ───────────────────────────────────────────────────────
function uptimeColor(pct: number | null): string {
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

const uptimeWindows = computed(() => [
    { label: '24h', value: props.stats.uptime_24h },
    { label: '7d', value: props.stats.uptime_7d },
    { label: '30d', value: props.stats.uptime_30d },
]);

const strong = computed(() => dark.value ? 'text-zinc-100' : 'text-zinc-800');
const soft = computed(() => dark.value ? 'text-zinc-400' : 'text-zinc-500');
const subtle = computed(() => dark.value ? 'text-zinc-500' : 'text-zinc-400');
const outline = computed(() => dark.value
    ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-700 hover:bg-white/[0.04]'
    : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300 hover:bg-zinc-50');
</script>

<template>
    <div class="flex flex-col gap-4">
        <slot />

        <!-- Connect -->
        <ServerPanel :title="t('servers.connect')">
            <div class="p-4 flex flex-col gap-2.5">
                <a :href="route('servers.connect', { game: game.slug, ip: server.ip, port: server.port })"
                    class="flex items-center justify-center gap-2 w-full py-3 rounded-xl text-[14px] font-bold text-white transition hover:opacity-90 active:scale-[0.98]"
                    :style="{ backgroundColor: game.color }">
                    <ExternalLink :size="15" :stroke-width="2" aria-hidden="true" />
                    {{ t('servers.connect') }}
                </a>
                <button type="button"
                    class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-medium border transition"
                    :class="outline" @click="ip.copy(server.address)">
                    <component :is="ip.copied.value ? Check : Copy" :size="14" :stroke-width="1.8" :class="ip.copied.value ? 'text-emerald-500' : ''" aria-hidden="true" />
                    <span :class="ip.copied.value ? 'text-emerald-500' : ''">{{ ip.copied.value ? t('servers.copied') : t('servers.copy_ip') }}</span>
                </button>
                <button type="button"
                    class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-medium border transition"
                    :class="favourited
                        ? 'border-red-500/30 bg-red-500/10 text-red-400'
                        : dark
                            ? 'border-zinc-800/70 text-zinc-400 hover:text-red-400 hover:border-red-500/25 hover:bg-red-500/5'
                            : 'border-zinc-200 text-zinc-500 hover:text-red-500 hover:border-red-300 hover:bg-red-50/50'"
                    :aria-pressed="favourited"
                    :disabled="favouriteBusy"
                    @click="toggleFavourite">
                    <Heart :size="14" :stroke-width="1.8" :fill="favourited ? 'currentColor' : 'none'" aria-hidden="true" />
                    {{ favourited ? t('servers.unfavourite') : t('servers.favourite') }}
                </button>
                <button type="button"
                    class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl text-[13px] font-medium border transition"
                    :class="outline" @click="pageLink.copy(route('servers.show', { game: game.slug, ip: server.ip, port: server.port }))">
                    <component :is="pageLink.copied.value ? Check : Share2" :size="14" :stroke-width="1.8" :class="pageLink.copied.value ? 'text-emerald-500' : ''" aria-hidden="true" />
                    <span :class="pageLink.copied.value ? 'text-emerald-500' : ''">{{ pageLink.copied.value ? t('servers.link_copied') : t('servers.copy_link') }}</span>
                </button>
            </div>
        </ServerPanel>

        <!-- Details -->
        <ServerPanel :title="t('servers.details_title')" :icon="Info">
            <div v-if="mapImage && !mapImageFailed" class="relative border-b" :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                <img :src="mapImage" :alt="server.status?.map ?? ''" class="w-full h-28 object-cover" loading="lazy" @error="mapImageFailed = true" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" aria-hidden="true" />
                <p class="absolute bottom-2 left-4 text-[12px] font-mono font-semibold text-white flex items-center gap-1.5">
                    <Map :size="11" :stroke-width="2" aria-hidden="true" />{{ server.status?.map }}
                </p>
            </div>

            <dl class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px]" :class="subtle">{{ t('servers.detail_game') }}</dt>
                    <dd class="text-[13px] font-medium" :class="soft">{{ game.name }}</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px]" :class="subtle">{{ t('servers.detail_address') }}</dt>
                    <dd class="font-mono text-[12px]" :class="soft">{{ server.address }}</dd>
                </div>
                <div v-if="server.status?.map" class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px] flex items-center gap-1.5" :class="subtle"><Map :size="12" :stroke-width="1.8" aria-hidden="true" />{{ t('servers.map') }}</dt>
                    <dd class="text-[13px] font-medium" :class="soft">{{ server.status.map }}</dd>
                </div>
                <div v-if="server.country_code" class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px] flex items-center gap-1.5" :class="subtle"><Globe2 :size="12" :stroke-width="1.8" aria-hidden="true" />{{ t('servers.detail_country') }}</dt>
                    <dd class="px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide"
                        :class="dark ? 'bg-zinc-800 text-zinc-300' : 'bg-zinc-200 text-zinc-500'">{{ server.country_code.toUpperCase() }}</dd>
                </div>
                <div v-if="server.status?.ping != null" class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px] flex items-center gap-1.5" :class="subtle"><Wifi :size="12" :stroke-width="1.8" aria-hidden="true" />{{ t('servers.ping') }}</dt>
                    <dd class="text-[13px] font-semibold tabular-nums" :class="pingClass(server.status, dark)">{{ server.status.ping }}ms</dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px] flex items-center gap-1.5" :class="subtle"><Shield :size="12" :stroke-width="1.8" aria-hidden="true" />{{ t('servers.detail_vac') }}</dt>
                    <dd class="flex items-center gap-1 text-[12px] font-medium"
                        :class="server.status?.vac_secured ? (dark ? 'text-emerald-400' : 'text-emerald-700') : subtle">
                        <component :is="server.status?.vac_secured ? CircleCheck : CircleX" :size="13" :stroke-width="1.8" aria-hidden="true" />
                        {{ server.status?.vac_secured ? t('servers.vac_on') : t('servers.vac_off') }}
                    </dd>
                </div>
                <div class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px] flex items-center gap-1.5" :class="subtle"><Lock :size="12" :stroke-width="1.8" aria-hidden="true" />{{ t('servers.detail_password') }}</dt>
                    <dd class="flex items-center gap-1 text-[12px] font-medium"
                        :class="server.status?.is_password_protected ? (dark ? 'text-amber-400' : 'text-amber-700') : subtle">
                        <component :is="server.status?.is_password_protected ? Lock : CircleCheck" :size="13" :stroke-width="1.8" aria-hidden="true" />
                        {{ server.status?.is_password_protected ? t('servers.password_required') : t('servers.password_none') }}
                    </dd>
                </div>
                <div v-if="server.status?.game_version" class="flex items-center justify-between px-5 py-3">
                    <dt class="text-[12px]" :class="subtle">{{ t('servers.version') }}</dt>
                    <dd class="text-[12px] font-mono" :class="soft">{{ server.status.game_version }}</dd>
                </div>
                <div v-if="server.tags.length" class="flex items-start justify-between px-5 py-3 gap-3">
                    <dt class="text-[12px] flex items-center gap-1.5 shrink-0 pt-0.5" :class="subtle"><Tag :size="12" :stroke-width="1.8" aria-hidden="true" />{{ t('servers.tags') }}</dt>
                    <dd class="flex flex-wrap gap-1 justify-end">
                        <span v-for="tag in server.tags" :key="tag" class="text-[10px] font-medium px-2 py-0.5 rounded border"
                            :class="dark ? 'border-zinc-800 text-zinc-400' : 'border-zinc-200 text-zinc-500'">{{ tag }}</span>
                    </dd>
                </div>
            </dl>
        </ServerPanel>

        <!-- Counters -->
        <ServerPanel :title="t('servers.stats_title')" :icon="Activity">
            <dl class="grid grid-cols-2" :class="dark ? '[&>*]:border-zinc-800/50' : '[&>*]:border-zinc-100'">
                <div v-for="(stat, i) in counters" :key="stat.label"
                    class="flex flex-col px-5 py-4"
                    :class="[i < 2 ? 'border-b' : '', i % 2 === 0 ? 'border-r' : '']">
                    <dt class="order-3 text-[10px] uppercase tracking-wide" :class="subtle">{{ stat.label }}</dt>
                    <dd class="order-1 mb-2.5"><component :is="stat.icon" :size="14" :stroke-width="1.8" :style="{ color: stat.color }" aria-hidden="true" /></dd>
                    <dd class="order-2 text-[20px] font-bold leading-none tabular-nums mb-1" :class="strong">{{ stat.value }}</dd>
                </div>
            </dl>
        </ServerPanel>

        <!-- Uptime (last 30 days) -->
        <ServerPanel :title="t('servers.uptime_title')" :icon="TrendingUp">
            <template #aside>
                <span class="text-[11px]" :class="subtle">{{ t('servers.uptime_window') }}</span>
            </template>
            <div class="p-4">
                <div class="flex items-end gap-[2px] h-8" role="img" :aria-label="t('servers.uptime_title')">
                    <div v-for="day in uptimeDaily" :key="day.date"
                        class="flex-1 rounded-sm transition-opacity hover:opacity-70"
                        :style="{ backgroundColor: uptimeColor(day.pct), height: day.pct === null ? '30%' : Math.max(15, day.pct) + '%' }"
                        :title="uptimeTooltip(day)" />
                </div>
                <div class="flex items-center justify-between mt-1.5 text-[10px]" :class="subtle">
                    <span>{{ t('servers.uptime_ago') }}</span>
                    <span>{{ t('servers.uptime_today') }}</span>
                </div>

                <div class="grid grid-cols-3 mt-4 pt-3 border-t text-center" :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                    <div v-for="w in uptimeWindows" :key="w.label">
                        <p class="text-[15px] font-bold tabular-nums leading-none"
                            :style="{ color: w.value === null ? undefined : uptimeColor(w.value) }"
                            :class="w.value === null ? subtle : ''">
                            {{ w.value === null ? '—' : w.value + '%' }}
                        </p>
                        <p class="text-[10px] uppercase tracking-wide mt-1" :class="subtle">{{ w.label }}</p>
                    </div>
                </div>
            </div>
        </ServerPanel>
    </div>
</template>
