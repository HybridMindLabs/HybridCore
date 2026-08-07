<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Users, Eye, Bot, TrendingUp, TrendingDown, FileText, Activity, Info, ExternalLink, PieChart } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import type Highcharts from 'highcharts';

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

// ── Dark mode (charts need to repaint on theme toggle) ────────────────────────
const dark = ref(document.documentElement.classList.contains('dark'));
const observer = new MutationObserver(() => {
    dark.value = document.documentElement.classList.contains('dark');
});
onMounted(() => observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] }));
onUnmounted(() => observer.disconnect());

const C = {
    blue: '#3b82f6', violet: '#8b5cf6', emerald: '#10b981', amber: '#f59e0b', zinc: '#71717a',
    grid: () => dark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)',
    label: () => dark.value ? '#71717a' : '#9ca3af',
};
const tooltipStyle = () => ({
    backgroundColor: dark.value ? '#18181b' : '#ffffff',
    borderColor: dark.value ? '#3f3f46' : '#e4e4e7',
    borderRadius: 10, borderWidth: 1, shadow: true,
    style: { color: dark.value ? '#f4f4f5' : '#18181b', fontSize: '12px', fontFamily: 'inherit' },
    padding: 10,
});

const botPercent = computed(() =>
    props.today.total > 0 ? Math.round((props.today.bots / props.today.total) * 100) : 0,
);
const conversionPercent = computed(() =>
    props.today.unique > 0 ? Math.round((props.today.registered / props.today.unique) * 100) : 0,
);
const deviceTotal = computed(() =>
    (props.devices.desktop ?? 0) + (props.devices.mobile ?? 0) + (props.devices.tablet ?? 0),
);

// vs yesterday, derived from the 30-day chart already on the page — no extra request.
const viewsTrend = computed(() => {
    if (props.chart.length < 2) return null;
    const yest = props.chart[props.chart.length - 2].total;
    const today = props.chart[props.chart.length - 1].total;
    if (yest === 0) return today > 0 ? { pct: 100, up: true } : null;
    const pct = Math.round(((today - yest) / yest) * 100);
    return { pct: Math.abs(pct), up: pct >= 0 };
});

const kpis = computed(() => [
    {
        icon: Eye, label: 'Total Views', value: props.today.total.toLocaleString(),
        color: 'text-blue-400', bg: 'bg-blue-500/10 border-blue-500/20',
        help: 'Every page request today, humans and bots combined.',
    },
    {
        icon: Users, label: 'Human Visits', value: props.today.humans.toLocaleString(),
        color: 'text-emerald-400', bg: 'bg-emerald-500/10 border-emerald-500/20',
        help: 'Page views today with bot traffic excluded.',
    },
    {
        icon: TrendingUp, label: 'Unique Sessions', value: props.today.unique.toLocaleString(),
        color: 'text-violet-400', bg: 'bg-violet-500/10 border-violet-500/20',
        help: 'Distinct human visitors today, counted once regardless of how many pages they viewed.',
    },
    {
        icon: Users, label: 'Registered', value: `${props.today.registered.toLocaleString()} (${conversionPercent.value}%)`,
        color: 'text-amber-400', bg: 'bg-amber-500/10 border-amber-500/20',
        help: 'Unique sessions that were logged in. Percentage is of unique sessions today.',
    },
    {
        icon: Bot, label: 'Bots', value: `${props.today.bots.toLocaleString()} (${botPercent.value}%)`,
        color: 'text-zinc-500', bg: 'bg-zinc-800/60 border-zinc-700/40',
        help: 'Requests identified as crawlers or automated traffic. Excluded from all other metrics.',
    },
]);

// ── Chart: visits (humans + bots stacked, unique sessions overlaid) ──────────
const trendRef = ref<HTMLElement | null>(null);
let trendChart: Highcharts.Chart | null = null;

function trendOptions(): Highcharts.Options {
    const cats = props.chart.map(p => {
        const [, m, d] = p.date.split('-');
        return `${d}/${m}`;
    });
    const humans = props.chart.map(p => p.total - p.bots);
    const bots = props.chart.map(p => p.bots);
    const unique = props.chart.map(p => p.unique);

    return {
        chart: { backgroundColor: 'transparent', height: 280, animation: { duration: 500 }, style: { fontFamily: 'inherit' } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: {
            align: 'right', verticalAlign: 'top',
            itemStyle: { color: C.label(), fontWeight: '500', fontSize: '12px' },
            itemHoverStyle: { color: dark.value ? '#fff' : '#000' },
        },
        xAxis: {
            categories: cats, tickInterval: 5,
            labels: { style: { color: C.label(), fontSize: '11px' } },
            lineColor: C.grid(), tickColor: 'transparent',
        },
        yAxis: {
            min: 0, title: { text: undefined },
            gridLineColor: C.grid(),
            labels: { style: { color: C.label(), fontSize: '11px' } },
        },
        tooltip: { ...tooltipStyle(), shared: true },
        plotOptions: {
            area: { stacking: 'normal', marker: { enabled: false }, lineWidth: 1.5 },
            spline: { marker: { enabled: false }, lineWidth: 2, dashStyle: 'ShortDot' },
        },
        series: [
            {
                type: 'area', name: 'Human views', data: humans, color: C.blue,
                fillColor: { linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 }, stops: [[0, 'rgba(59,130,246,0.35)'], [1, 'rgba(59,130,246,0.02)']] },
            },
            {
                type: 'area', name: 'Bot views', data: bots, color: C.zinc,
                fillColor: { linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 }, stops: [[0, 'rgba(113,113,122,0.30)'], [1, 'rgba(113,113,122,0.02)']] },
            },
            { type: 'spline', name: 'Unique sessions', data: unique, color: C.violet },
        ],
    };
}

// ── Chart: devices donut ──────────────────────────────────────────────────────
const deviceRef = ref<HTMLElement | null>(null);
let deviceChart: Highcharts.Chart | null = null;

function deviceOptions(): Highcharts.Options {
    const rows = [
        { name: 'Desktop', y: props.devices.desktop ?? 0, color: C.blue },
        { name: 'Mobile', y: props.devices.mobile ?? 0, color: C.violet },
        { name: 'Tablet', y: props.devices.tablet ?? 0, color: C.amber },
    ];
    const total = deviceTotal.value;

    return {
        chart: {
            type: 'pie', backgroundColor: 'transparent', height: 260, animation: { duration: 500 }, style: { fontFamily: 'inherit' },
            events: {
                render(): void {
                    const chart = this as unknown as Highcharts.Chart & { hcCenterLabel?: Highcharts.SVGElement };
                    const series = chart.series[0] as unknown as { center?: number[] } | undefined;
                    if (!series?.center) return;
                    const x = chart.plotLeft + series.center[0];
                    const y = chart.plotTop + series.center[1];
                    if (!chart.hcCenterLabel) {
                        chart.hcCenterLabel = chart.renderer.text('', 0, 0, true).css({ pointerEvents: 'none' }).add();
                    }
                    chart.hcCenterLabel.attr({
                        x, y,
                        text: `<div style="transform:translate(-50%,-50%);text-align:center;line-height:1.15">`
                            + `<div style="font-size:20px;font-weight:800;color:${dark.value ? '#f4f4f5' : '#18181b'}">${total.toLocaleString()}</div>`
                            + `<div style="font-size:10px;color:#71717a;margin-top:2px;letter-spacing:.05em;text-transform:uppercase">views</div>`
                            + `</div>`,
                    });
                },
            },
        },
        title: { text: undefined },
        credits: { enabled: false },
        tooltip: { ...tooltipStyle(), pointFormat: '<b>{point.y}</b> views ({point.percentage:.1f}%)' },
        legend: {
            layout: 'vertical', align: 'right', verticalAlign: 'middle',
            itemStyle: { color: C.label(), fontWeight: '500', fontSize: '12px' },
            itemHoverStyle: { color: dark.value ? '#fff' : '#000' },
        },
        plotOptions: {
            pie: {
                innerSize: '60%',
                dataLabels: { enabled: true, distance: -28, format: '{point.percentage:.0f}%', style: { color: '#fff', fontSize: '10px', fontWeight: '700', textOutline: 'none' }, filter: { property: 'percentage', operator: '>', value: 6 } },
                showInLegend: true, borderWidth: 0,
                states: { hover: { brightness: 0.08 } },
            },
        },
        series: [{ type: 'pie', name: 'Views', data: rows }],
    };
}

let HC: typeof Highcharts | null = null;
async function loadHC() {
    const m = await import('highcharts');
    HC = m.default;
}
function mkChart(el: HTMLElement | null, opts: Highcharts.Options): Highcharts.Chart | null {
    if (!el || !HC) return null;
    return HC.chart(el, opts);
}
function rebuildAll() {
    trendChart?.destroy(); trendChart = mkChart(trendRef.value, trendOptions());
    if (deviceTotal.value > 0) {
        deviceChart?.destroy(); deviceChart = mkChart(deviceRef.value, deviceOptions());
    }
}
onMounted(async () => { await loadHC(); rebuildAll(); });
onUnmounted(() => { trendChart?.destroy(); deviceChart?.destroy(); });
watch(dark, () => rebuildAll());
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
                v-for="(card, i) in kpis"
                :key="card.label"
                class="hc-hero-in rounded-xl border p-4 flex flex-col gap-3"
                :class="card.bg"
                :style="{ animationDelay: `${i * 40}ms` }"
            >
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
                        {{ card.label }}
                        <Tooltip :text="card.help">
                            <Info :size="11" :stroke-width="2" class="text-zinc-700 hover:text-zinc-400 transition-colors" />
                        </Tooltip>
                    </span>
                    <component :is="card.icon" :size="14" :stroke-width="1.75" :class="card.color" />
                </div>
                <div class="flex items-end justify-between gap-2">
                    <p class="text-2xl font-bold tabular-nums text-zinc-100 leading-none">{{ card.value }}</p>
                    <span
                        v-if="card.label === 'Total Views' && viewsTrend"
                        class="flex items-center gap-0.5 text-[11px] font-semibold shrink-0"
                        :class="viewsTrend.up ? 'text-emerald-400' : 'text-red-400'"
                    >
                        <component :is="viewsTrend.up ? TrendingUp : TrendingDown" :size="11" :stroke-width="2" />
                        {{ viewsTrend.pct }}%
                    </span>
                </div>
            </div>
        </div>

        <!-- Chart + Devices -->
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-4 mb-4">

            <!-- Visits trend -->
            <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl p-5" style="animation-delay: 200ms">
                <div class="flex items-center gap-1.5 mb-1">
                    <h2 class="text-sm font-semibold text-zinc-100">Visits — last 30 days</h2>
                    <Tooltip text="Human and bot page views per day, plus unique human sessions as a dotted overlay.">
                        <Info :size="12" :stroke-width="2" class="text-zinc-700 hover:text-zinc-400 transition-colors" />
                    </Tooltip>
                </div>
                <p class="text-zinc-600 text-xs mb-3">Stacked views split by traffic type, with unique sessions overlaid for context.</p>
                <div ref="trendRef" />
            </div>

            <!-- Device breakdown -->
            <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl p-5" style="animation-delay: 240ms">
                <div class="flex items-center gap-1.5 mb-1">
                    <h2 class="text-sm font-semibold text-zinc-100">Devices · Today</h2>
                    <Tooltip text="Device type of every human page view recorded today.">
                        <Info :size="12" :stroke-width="2" class="text-zinc-700 hover:text-zinc-400 transition-colors" />
                    </Tooltip>
                </div>
                <p class="text-zinc-600 text-xs mb-3">How visitors are reaching the site right now.</p>

                <div v-if="deviceTotal > 0" ref="deviceRef" />
                <div v-else class="flex flex-col items-center justify-center py-14 text-center">
                    <PieChart :size="24" :stroke-width="1.5" class="text-zinc-800 mb-3" />
                    <p class="text-zinc-500 text-sm">No device data yet today.</p>
                </div>
            </div>
        </div>

        <!-- Top pages -->
        <div class="hc-hero-in bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden" style="animation-delay: 280ms">
            <div class="flex items-center gap-2 px-5 py-3 border-b border-zinc-800/70">
                <FileText :size="14" :stroke-width="1.75" class="text-zinc-600" />
                <h2 class="text-sm font-semibold text-zinc-100">Top Pages · Today</h2>
                <Tooltip text="Ranked by human page views today — bot traffic is excluded.">
                    <Info :size="12" :stroke-width="2" class="text-zinc-700 hover:text-zinc-400 transition-colors" />
                </Tooltip>
                <span class="ml-auto text-xs text-zinc-600">{{ pages.length }} pages</span>
            </div>

            <div v-if="pages.length">
                <a
                    v-for="(page, i) in pages"
                    :key="page.path"
                    :href="'/' + page.path"
                    target="_blank"
                    class="flex items-center gap-4 px-5 py-3 border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors group"
                >
                    <span
                        class="text-xs font-bold w-5 text-right tabular-nums shrink-0"
                        :class="i < 3 ? 'text-blue-400' : 'text-zinc-700'"
                    >{{ i + 1 }}</span>
                    <span class="text-sm text-zinc-300 font-mono truncate flex-1 group-hover:text-blue-400 transition-colors">/{{ page.path }}</span>
                    <ExternalLink :size="11" :stroke-width="1.75" class="text-zinc-700 group-hover:text-zinc-400 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" />
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
                </a>
            </div>

            <div v-else class="py-16 text-center">
                <Activity :size="28" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-3" />
                <p class="text-zinc-500 text-sm">No data yet for today.</p>
            </div>
        </div>

    </AdminLayout>
</template>
