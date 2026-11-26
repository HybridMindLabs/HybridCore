<script setup lang="ts">
import { computed } from 'vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import GameIcon from '@/components/UI/GameIcon.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { cssUrl, type GameInfo } from './types';

const props = defineProps<{
    game: GameInfo;
    stats: { total: number; online: number; players: number };
    totalCount: number;
    favouriteCount: number;
}>();

const activeTab = defineModel<'all' | 'favourites'>('tab', { required: true });

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const tabs = computed<{ key: 'all' | 'favourites'; label: string; hint: string; count: number }[]>(() => [
    { key: 'all', label: t('servers.filter_all'), hint: t('servers.tab_all_hint'), count: props.totalCount },
    { key: 'favourites', label: t('servers.favourite'), hint: t('servers.tab_favourites_hint'), count: props.favouriteCount },
]);

// Every figure carries an explanation, so nobody has to guess what "online" counts.
const heroStats = computed(() => [
    { value: props.stats.total, label: t('servers.servers_label'), hint: t('servers.hero_total_hint'), accent: false },
    { value: props.stats.online, label: t('servers.online_now'), hint: t('servers.hero_online_hint'), accent: props.stats.online > 0 },
    { value: props.stats.players, label: t('servers.players_label'), hint: t('servers.hero_players_hint'), accent: false },
]);
</script>

<template>
    <section class="relative overflow-hidden" style="min-height: 210px"
        :aria-label="t('servers.game_servers_title', { game: game.name })">
        <div v-if="game.cover_url" class="absolute inset-0 overflow-hidden" aria-hidden="true">
            <div
                class="absolute inset-[-10%] bg-cover bg-center"
                :style="{
                    backgroundImage: cssUrl(game.cover_url),
                    filter: dark
                        ? 'blur(16px) brightness(0.42) saturate(1.2)'
                        : 'blur(16px) brightness(1.06) saturate(1.1)',
                }"
            />
        </div>
        <div v-else class="absolute inset-0" aria-hidden="true"
            :style="{ background: `linear-gradient(135deg, ${game.color}40, ${game.color}10)` }" />

        <!-- Each theme scrims with its own page colour, strong under the text
             on the left and fading out to the right. -->
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

        <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 pb-0">
            <Breadcrumb :items="[
                { label: t('navigation.nav_home'), href: route('home') },
                { label: t('servers.title'), href: route('servers.index') },
                { label: game.name },
            ]" />

            <div class="grid gap-6 lg:gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)] lg:items-end mt-4 mb-5">
                <div class="max-w-xl">
                    <div class="hc-hero-in inline-flex items-center gap-2 px-3 py-1 rounded-full border text-[10.5px] font-bold uppercase tracking-widest"
                        :class="stats.online > 0
                            ? (dark ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-800')
                            : (dark ? 'border-zinc-700 bg-zinc-800/60 text-zinc-400' : 'border-zinc-300 bg-zinc-200/70 text-zinc-500')"
                        aria-live="polite">
                        <span v-if="stats.online > 0" class="hc-live-dot" aria-hidden="true" />
                        {{ t('servers.game_live_badge', { online: stats.online, total: stats.total }) }}
                    </div>

                    <div class="hc-hero-in hc-hero-in--1 flex items-center gap-4 mt-4">
                        <div class="w-14 h-14 rounded-xl shrink-0 shadow-lg ring-2 overflow-hidden"
                            :class="dark ? 'ring-zinc-800' : 'ring-white'">
                            <GameIcon :slug="game.slug" :alt="game.name" />
                        </div>
                        <h1 class="text-[26px] sm:text-[34px] font-black leading-tight tracking-tight"
                            :class="dark ? 'text-white' : 'text-zinc-900'">
                            {{ t('servers.game_servers_title', { game: game.name }) }}
                        </h1>
                    </div>

                    <p class="hc-hero-in hc-hero-in--2 mt-3 text-[14px] sm:text-[15px] leading-relaxed max-w-lg"
                       :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        {{ t('servers.game_hero_description', { game: game.name }) }}
                    </p>
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
                        <dd class="order-1 text-[20px] font-black leading-none tabular-nums"
                            :class="item.accent
                                ? (dark ? 'text-emerald-400' : 'text-emerald-800')
                                : (dark ? 'text-zinc-100' : 'text-zinc-900')">
                            {{ item.value.toLocaleString() }}
                        </dd>
                        <dd class="order-3 text-[10.5px] leading-snug mt-1 text-zinc-500">{{ item.hint }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex items-center gap-0.5 border-b" :class="dark ? 'border-white/10' : 'border-zinc-300'">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="flex items-center gap-2 px-5 py-3 text-[13px] font-semibold border-b-2 -mb-px transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                    :class="activeTab === tab.key
                        ? dark ? 'text-white border-white' : 'text-zinc-900 border-zinc-900'
                        : dark ? 'text-white/45 border-transparent hover:text-white/75' : 'text-zinc-500 border-transparent hover:text-zinc-900'"
                    :aria-pressed="activeTab === tab.key"
                    :title="tab.hint"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold tabular-nums"
                        :class="dark ? 'bg-zinc-800 text-zinc-400' : 'bg-zinc-200 text-zinc-500'">{{ tab.count }}</span>
                </button>
            </div>
        </div>
    </section>
</template>
