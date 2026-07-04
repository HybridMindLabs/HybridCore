<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Users, Eye, Bot, TrendingUp, Monitor, Smartphone, Tablet, FileText, Activity } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface DaySummary { total: number; bots: number; humans: number; unique: number; registered: number }
interface ChartPoint { date: string; total: number; unique: number; bots: number }
interface TopPage { path: string; views: number }
interface Devices { desktop?: number; mobile?: number; tablet?: number }

const props = defineProps<{
    today: DaySummary;
    chart: ChartPoint[];
    pages: TopPage[];
    devices: Devices;
    online: number;
}>();

const botPercent = computed(() =>
    props.today.total > 0 ? Math.round((props.today.bots / props.today.total) * 100) : 0,
);

const deviceTotal = computed(() =>
    (props.devices.desktop ?? 0) + (props.devices.mobile ?? 0) + (props.devices.tablet ?? 0),
);

function devicePct(val: number | undefined): number {
    return deviceTotal.value > 0 ? Math.round(((val ?? 0) / deviceTotal.value) * 100) : 0;
}

const chartMax = computed(() => Math.max(...props.chart.map((r) => r.total), 1));

function barHeight(val: number): number {
    return Math.max(2, Math.round((val / chartMax.value) * 100));
}

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('en', { month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head title="Analytics" />
    <AdminLayout title="Analytics">

        <PageHeader title="Analytics" description="Traffic overview for today." :icon="Activity">
            <template #actions>
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0" />
                    <span class="text-xs font-semibold text-emerald-400">{{ online }} online now</span>
                </div>
            </template>
        </PageHeader>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div
                v-for="card in [
                    { icon: Eye,        label: 'Total Views',     value: today.total.toLocaleString(),                                 color: 'text-blue-400',    bg: 'bg-blue-500/10 border-blue-500/20'       },
                    { icon: Users,      label: 'Human Visits',    value: today.humans.toLocaleString(),                                color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/20' },
                    { icon: TrendingUp, label: 'Unique Sessions', value: today.unique.toLocaleString(),                                color: 'text-violet-400',  bg: 'bg-violet-500/10 border-violet-500/20'   },
                    { icon: Users,      label: 'Registered',      value: today.registered.toLocaleString(),                            color: 'text-amber-400',   bg: 'bg-amber-500/10 border-amber-500/20'     },
                    { icon: Bot,        label: 'Bots',            value: `${today.bots.toLocaleString()} (${botPercent}%)`,            color: 'text-zinc-500',    bg: 'bg-zinc-800/60 border-zinc-700/40'       },
                ]"
                :key="card.label"
                class="rounded-xl border p-4 flex flex-col gap-3"
                :class="card.bg"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">{{ card.label }}</span>
                    <component :is="card.icon" :size="14" :stroke-width="1.75" :class="card.color" />
                </div>
                <p class="text-2xl font-bold tabular-nums text-zinc-100 leading-none">{{ card.value }}</p>
            </div>
        </div>

        <!-- Chart + Devices -->
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px] gap-4 mb-4">

            <!-- Bar chart -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-sm font-semibold text-zinc-100">Visits — last 30 days</h2>
                    <div class="flex items-center gap-4 text-xs text-zinc-600">
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-blue-500/70 inline-block" />Humans
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-zinc-700 inline-block" />Bots
                        </span>
                    </div>
                </div>
                <div class="flex items-end gap-[3px] h-[120px]">
                    <div
                        v-for="point in chart"
                        :key="point.date"
                        class="flex-1 flex flex-col-reverse gap-[1px] group relative cursor-default"
                        :title="`${formatDate(point.date)}: ${point.total - point.bots} humans, ${point.bots} bots`"
                    >
                        <div
                            class="w-full rounded-t-sm bg-blue-500/60 group-hover:bg-blue-400/80 transition-colors"
                            :style="{ height: barHeight(point.total - point.bots) + '%' }"
                        />
                        <div
                            v-if="point.bots > 0"
                            class="w-full rounded-t-sm bg-zinc-700/80"
                            :style="{ height: barHeight(point.bots) + '%' }"
                        />
                    </div>
                </div>
                <div class="flex items-end gap-[3px] mt-1.5">
                    <div v-for="(point, i) in chart" :key="point.date" class="flex-1 text-center">
                        <span v-if="i % 5 === 0" class="text-[9px] text-zinc-700">{{ formatDate(point.date) }}</span>
                    </div>
                </div>
            </div>

            <!-- Device breakdown -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-zinc-100 mb-5">Devices · Today</h2>
                <div class="flex flex-col gap-4">
                    <div
                        v-for="dev in [
                            { key: 'desktop', icon: Monitor,    label: 'Desktop', color: 'bg-blue-500'   },
                            { key: 'mobile',  icon: Smartphone, label: 'Mobile',  color: 'bg-violet-500' },
                            { key: 'tablet',  icon: Tablet,     label: 'Tablet',  color: 'bg-amber-500'  },
                        ]"
                        :key="dev.key"
                        class="flex flex-col gap-1.5"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2 text-zinc-400">
                                <component :is="dev.icon" :size="13" :stroke-width="1.75" />
                                {{ dev.label }}
                            </div>
                            <span class="tabular-nums text-zinc-300">
                                {{ (devices[dev.key as keyof typeof devices] ?? 0).toLocaleString() }}
                                <span class="text-zinc-600">({{ devicePct(devices[dev.key as keyof typeof devices]) }}%)</span>
                            </span>
                        </div>
                        <div class="h-1.5 rounded-full overflow-hidden bg-zinc-800">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="dev.color"
                                :style="{ width: devicePct(devices[dev.key as keyof typeof devices]) + '%' }"
                            />
                        </div>
                    </div>
                </div>

                <div class="border-t border-zinc-800/50 mt-5 pt-4">
                    <p class="text-zinc-600 text-[11px] uppercase tracking-wider font-semibold mb-1.5">Total human pageviews</p>
                    <p class="text-3xl font-bold text-zinc-100 tabular-nums leading-none">
                        {{ deviceTotal.toLocaleString() }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Top pages -->
        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-zinc-800/70">
                <FileText :size="14" :stroke-width="1.75" class="text-zinc-600" />
                <h2 class="text-sm font-semibold text-zinc-100">Top Pages · Today</h2>
                <span class="ml-auto text-xs text-zinc-600">{{ pages.length }} pages</span>
            </div>

            <div v-if="pages.length">
                <div
                    v-for="(page, i) in pages"
                    :key="page.path"
                    class="flex items-center gap-4 px-5 py-3 border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors"
                >
                    <span
                        class="text-xs font-bold w-5 text-right tabular-nums shrink-0"
                        :class="i < 3 ? 'text-blue-400' : 'text-zinc-700'"
                    >{{ i + 1 }}</span>
                    <span class="text-sm text-zinc-300 font-mono truncate flex-1">/{{ page.path }}</span>
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="w-20 h-1.5 rounded-full overflow-hidden bg-zinc-800">
                            <div
                                class="h-full rounded-full bg-blue-500/60"
                                :style="{ width: (pages[0]?.views > 0 ? (page.views / pages[0].views) * 100 : 0) + '%' }"
                            />
                        </div>
                        <span class="text-xs font-semibold text-zinc-400 tabular-nums w-12 text-right">
                            {{ page.views.toLocaleString() }}
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="py-16 text-center">
                <Activity :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                <p class="text-zinc-500 text-sm">No data yet for today.</p>
            </div>
        </div>

    </AdminLayout>
</template>
