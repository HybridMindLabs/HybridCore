<script setup lang="ts">
import { computed, ref } from 'vue';
import PlayerAreaChart from '@/components/UI/PlayerAreaChart.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import type { InsightRange } from './types';

/**
 * Player history per range (24h / 7d / 30d) with average, peak and uptime,
 * plus an optional map split. Used by a game's server list and by a single
 * server's page.
 */
const props = withDefaults(defineProps<{
    ranges: Record<'24h' | '7d' | '30d', InsightRange>;
    title: string;
    subtitle?: string;
    maps?: { map: string; share: number }[] | null;
    chartHeight?: number;
}>(), { subtitle: '', maps: null, chartHeight: 240 });

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

type Tab = '24h' | '7d' | '30d' | 'maps';
const activeTab = ref<Tab>('24h');

const tabs = computed<{ key: Tab; label: string }[]>(() => [
    { key: '24h', label: t('servers.range_24h') },
    { key: '7d', label: t('servers.range_7d') },
    { key: '30d', label: t('servers.range_30d') },
    ...(props.maps !== null ? [{ key: 'maps' as const, label: t('servers.range_maps') }] : []),
]);

const range = computed(() => props.ranges[activeTab.value === 'maps' ? '24h' : activeTab.value]);

// Single values, so stat tiles rather than more charts.
const tiles = computed(() => [
    { label: t('servers.average_players'), value: range.value.average.toLocaleString(), suffix: '', hint: t('servers.average_players_hint') },
    { label: t('servers.peak_players'), value: range.value.peak.toLocaleString(), suffix: '', hint: t('servers.peak_players_hint') },
    { label: t('servers.uptime_label'), value: String(range.value.uptime), suffix: '%', hint: t('servers.uptime_label_hint') },
]);
</script>

<template>
    <section class="rounded-2xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
        :aria-label="title">

        <div class="flex items-center justify-between gap-4 px-5 py-3 border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
            <h2 class="text-[14px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ title }}</h2>
            <p v-if="subtitle" class="text-[11.5px] text-zinc-500 text-right">{{ subtitle }}</p>
        </div>

        <div class="flex items-center gap-0.5 px-3 border-b overflow-x-auto"
            :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'"
            role="group" :aria-label="t('servers.range_group')">
            <button v-for="tab in tabs" :key="tab.key" type="button"
                class="px-4 py-2.5 text-[12.5px] font-semibold border-b-2 -mb-px whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                :class="activeTab === tab.key
                    ? 'text-blue-500 border-blue-500'
                    : dark ? 'text-zinc-500 border-transparent hover:text-zinc-200' : 'text-zinc-500 border-transparent hover:text-zinc-900'"
                :aria-pressed="activeTab === tab.key"
                @click="activeTab = tab.key">
                {{ tab.label }}
            </button>
        </div>

        <div v-if="activeTab === 'maps' && maps" class="px-5 py-5">
            <ul v-if="maps.length" class="flex flex-col gap-2.5">
                <li v-for="m in maps" :key="m.map" class="flex items-center gap-3">
                    <span class="w-[150px] shrink-0 text-[12.5px] font-mono truncate"
                        :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ m.map }}</span>
                    <span class="flex-1 h-3.5 rounded-sm overflow-hidden" :class="dark ? 'bg-zinc-800/70' : 'bg-zinc-100'">
                        <span class="hc-hero-bar block h-full rounded-r-[4px] bg-blue-500" :style="{ width: Math.max(2, m.share) + '%' }" />
                    </span>
                    <span class="w-10 shrink-0 text-right text-[12px] font-bold tabular-nums"
                        :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ m.share }}%</span>
                </li>
            </ul>
            <p v-else class="text-[12.5px] py-6 text-center text-zinc-500">{{ t('servers.not_enough_data') }}</p>
            <p v-if="maps.length" class="text-[11px] mt-3 text-zinc-500">{{ t('servers.maps_hint') }}</p>
        </div>

        <template v-else>
            <div class="px-5 pt-5 pb-2">
                <PlayerAreaChart :data="range.history" :dark="dark" :height="chartHeight"
                    :label="t('servers.players_over_time')" :empty-label="t('servers.not_enough_data')" />
            </div>

            <div class="grid grid-cols-3 gap-px border-t"
                :class="dark ? 'bg-zinc-800/60 border-zinc-800/60' : 'bg-zinc-200 border-zinc-200'">
                <div v-for="tile in tiles" :key="tile.label"
                    class="px-4 py-4 text-center" :class="dark ? 'bg-[#111113]' : 'bg-white'"
                    :title="tile.hint">
                    <p class="text-[11.5px] font-semibold text-zinc-500">{{ tile.label }}</p>
                    <p class="text-[22px] sm:text-[26px] font-black leading-none tabular-nums mt-1.5" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ tile.value }}<span v-if="tile.suffix" class="text-[14px] font-bold text-zinc-500">{{ tile.suffix }}</span>
                    </p>
                </div>
            </div>
        </template>
    </section>
</template>
