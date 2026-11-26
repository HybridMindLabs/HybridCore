<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock, Map, Shield, Star } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import ServerCopyButton from './ServerCopyButton.vue';
import { cssUrl, pingClass, playerBarColor, type GameInfo, type ServerStatus } from './types';

const props = defineProps<{
    game: GameInfo;
    server: {
        name: string; address: string; country_code: string | null; tags: string[];
        status: ServerStatus | null;
    };
    uptime24h: number;
    averageRating: number | null;
    reviewCount: number;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

// Every figure explains itself, and the player count keeps its capacity bar.
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
            class: null as string | null,
            bar: status?.is_online ? capacity : null,
            barColor: playerBarColor(status),
        },
        {
            label: t('servers.col_ping'),
            value: status?.ping != null ? status.ping + 'ms' : '—',
            suffix: '',
            hint: t('servers.hero_ping_hint'),
            class: pingClass(status, dark.value),
            bar: null,
            barColor: '',
        },
        {
            label: t('servers.uptime_label'),
            value: String(props.uptime24h),
            suffix: '%',
            hint: t('servers.hero_uptime_hint'),
            class: null,
            bar: null,
            barColor: '',
        },
    ];
});
</script>

<template>
    <div class="relative overflow-hidden" style="min-height: 230px">
        <div v-if="game.cover_url" class="absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute inset-[-10%] bg-cover bg-center"
                :style="{ backgroundImage: cssUrl(game.cover_url), filter: dark ? 'blur(18px) brightness(0.42) saturate(1.2)' : 'blur(18px) brightness(1.06) saturate(1.1)' }" />
        </div>
        <div v-else class="absolute inset-0" aria-hidden="true" :style="{ background: `linear-gradient(135deg, ${game.color}40, ${game.color}10)` }" />
        <!-- Each theme scrims with its own page colour. -->
        <div class="absolute inset-0" aria-hidden="true"
            :style="dark
                ? 'background: linear-gradient(to right, rgba(9,9,11,0.88) 0%, rgba(9,9,11,0.70) 45%, transparent 85%)'
                : 'background: linear-gradient(to right, rgba(236,238,242,0.95) 0%, rgba(236,238,242,0.82) 45%, rgba(236,238,242,0.30) 85%)'" />
        <div class="absolute inset-0" aria-hidden="true"
            :style="dark
                ? 'background: linear-gradient(to bottom, transparent 40%, rgba(9,9,11,1) 100%)'
                : 'background: linear-gradient(to bottom, transparent 35%, rgba(236,238,242,1) 100%)'" />
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
                            <h1 class="text-[24px] sm:text-[30px] font-black leading-tight tracking-tight break-words"
                                :class="dark ? 'text-white' : 'text-zinc-900'">{{ server.name }}</h1>

                            <div class="flex items-center gap-2 flex-wrap mt-2">
                                <Link :href="route('servers.game', game.slug)"
                                    class="text-[12.5px] font-semibold transition-colors rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                    :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                                    {{ game.name }}
                                </Link>
                                <span v-if="averageRating"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full border border-amber-500/30 bg-amber-500/10"
                                    :class="dark ? 'text-amber-400' : 'text-amber-700'"
                                    :title="t('servers.rating_hint', { count: reviewCount })">
                                    <Star :size="10" :stroke-width="2" fill="currentColor" aria-hidden="true" />
                                    {{ Number(averageRating).toFixed(1) }}
                                </span>
                                <span v-if="server.country_code"
                                    class="px-2 py-0.5 rounded-full border text-[10px] font-bold tracking-wide"
                                    :class="dark ? 'border-zinc-700 bg-zinc-800/70 text-zinc-300' : 'border-zinc-300 bg-zinc-200/70 text-zinc-500'">{{ server.country_code.toUpperCase() }}</span>
                                <span v-if="server.status?.is_password_protected"
                                    class="inline-flex items-center gap-1 text-[10.5px] font-bold px-2 py-0.5 rounded-full border border-amber-500/30 bg-amber-500/10"
                                    :class="dark ? 'text-amber-400' : 'text-amber-700'">
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

                    <!-- The address is the thing people came for. -->
                    <div class="hc-hero-in hc-hero-in--2 flex items-center gap-2 mt-4 flex-wrap">
                        <div class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 rounded-xl border backdrop-blur-md"
                            :class="dark ? 'border-zinc-700/70 bg-zinc-900/80' : 'border-zinc-300 bg-white'">
                            <span class="font-mono text-[13.5px] font-semibold" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ server.address }}</span>
                            <ServerCopyButton :address="server.address" />
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
                            :class="dark ? 'bg-zinc-800/80 text-zinc-400' : 'bg-zinc-200/80 text-zinc-500'">{{ tag }}</span>
                    </div>
                </div>

                <dl class="hc-hero-in hc-hero-in--2 grid grid-cols-3 gap-2.5">
                    <div v-for="(item, i) in heroStats" :key="item.label"
                        class="hc-reveal flex flex-col rounded-xl border px-3 py-2.5 backdrop-blur-md"
                        :style="{ animationDelay: 0.18 + i * 0.06 + 's' }"
                        :class="dark
                            ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                            : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'"
                        :title="item.hint">
                        <!-- Term first, as a <dl> group requires; `order` puts the figure back above its label. -->
                        <dt class="order-2 text-[10px] font-bold uppercase tracking-widest mt-1.5 text-zinc-500">{{ item.label }}</dt>
                        <dd class="order-1 text-[19px] font-black leading-none tabular-nums"
                            :class="item.class ?? (dark ? 'text-zinc-100' : 'text-zinc-900')">
                            {{ item.value }}<span v-if="item.suffix" class="text-[12px] font-bold text-zinc-500">{{ item.suffix }}</span>
                        </dd>
                        <dd v-if="item.bar !== null" class="order-3 mt-2 h-1 rounded-full overflow-hidden"
                            :class="dark ? 'bg-zinc-800' : 'bg-zinc-200'" aria-hidden="true">
                            <span class="hc-hero-bar block h-full rounded-full" :style="{ width: item.bar + '%', backgroundColor: item.barColor }" />
                        </dd>
                        <dd v-else class="order-3 text-[10.5px] leading-snug mt-1 text-zinc-500">{{ item.hint }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>
