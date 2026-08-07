<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import Tooltip from '@/components/UI/Tooltip.vue';
import {
    Users, Server, MessageSquare, ShieldBan, BadgeCheck,
    TrendingUp, TrendingDown, UserPlus, ArrowRight, Puzzle, Paintbrush,
    Activity, BarChart2, PieChart, Wifi, AlertTriangle, Power,
    MousePointerClick, Gamepad2, Shapes, Info,
} from '@lucide/vue';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import type Highcharts from 'highcharts';

// ── Types ────────────────────────────────────────────────────────────────────
interface DayPoint { date: string; count: number }
interface RoleSlice { name: string; color: string; count: number }
interface GameBar { name: string; total: number }
interface Stats {
    total_users: number; new_users_today: number; new_users_week: number;
    banned_users: number; verified_users: number;
    servers_total: number | null; servers_online: number | null;
    total_messages: number | null;
    registrations_last_30_days: DayPoint[];
    logins_last_30_days: DayPoint[];
    role_distribution: RoleSlice[];
    top_games: GameBar[] | null;
    top_clicked_servers: { name: string; clicks: number }[];
    engagement: {
        reviews: number | null; reviews_week: number | null;
        comments: number | null; comments_week: number | null;
        follows: number | null; follows_week: number | null;
    };
}
interface Widget { id: string; title: string; component: string; permission: string | null; props: Record<string, unknown> }

const props = defineProps<{ widgets: Widget[]; stats?: Stats; maintenance_mode?: boolean }>();

function toggleMaintenance() {
    const route_name = props.maintenance_mode ? 'admin.maintenance.disable' : 'admin.maintenance.enable';
    router.post(route(route_name));
}

// ── Dark mode ─────────────────────────────────────────────────────────────────
const dark = ref(document.documentElement.classList.contains('dark'));
const observer = new MutationObserver(() => {
    dark.value = document.documentElement.classList.contains('dark');
});
onMounted(() => observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] }));
onUnmounted(() => observer.disconnect());

// ── Palette helpers ───────────────────────────────────────────────────────────
const C = {
    blue:    '#3b82f6',
    violet:  '#8b5cf6',
    emerald: '#10b981',
    amber:   '#f59e0b',
    rose:    '#f43f5e',
    cyan:    '#06b6d4',
    grid:    () => dark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)',
    label:   () => dark.value ? '#71717a' : '#9ca3af',
    bg:      () => 'transparent',
    tooltip: () => ({ bg: dark.value ? '#18181b' : '#ffffff', border: dark.value ? '#3f3f46' : '#e4e4e7' }),
};

const tooltipStyle = () => ({
    backgroundColor: C.tooltip().bg,
    borderColor: C.tooltip().border,
    borderRadius: 10,
    borderWidth: 1,
    shadow: true,
    style: { color: dark.value ? '#f4f4f5' : '#18181b', fontSize: '12px', fontFamily: 'inherit' },
    padding: 10,
});

// ── KPI cards ─────────────────────────────────────────────────────────────────
const kpis = computed(() => {
    const s = props.stats;
    if (!s) return [];
    const rows = [
        { label: 'Total users',    value: s.total_users,      icon: Users,       color: C.blue,    bg: 'rgba(59,130,246,0.08)', help: 'Every registered account, including banned and unverified ones.' },
        { label: 'New today',      value: s.new_users_today,  icon: UserPlus,    color: C.emerald, bg: 'rgba(16,185,129,0.08)', help: 'Accounts created since midnight, server time.' },
        { label: 'This week',      value: s.new_users_week,   icon: TrendingUp,  color: C.violet,  bg: 'rgba(139,92,246,0.08)', help: 'Accounts created in the last 7 days.' },
        { label: 'Banned',         value: s.banned_users,     icon: ShieldBan,   color: C.rose,    bg: 'rgba(244,63,94,0.08)',  help: 'Accounts currently under a ban — lifted bans are not counted.' },
        { label: 'Verified',       value: s.verified_users,   icon: BadgeCheck,  color: C.cyan,    bg: 'rgba(6,182,212,0.08)',  help: 'Accounts with a confirmed email address.' },
    ];
    if (s.servers_total !== null)  rows.push({ label: 'Servers',  value: s.servers_total,  icon: Server,         color: C.amber,   bg: 'rgba(245,158,11,0.08)', help: 'Every game server registered on the platform.' });
    if (s.servers_online !== null) rows.push({ label: 'Online',   value: s.servers_online, icon: Wifi,           color: C.emerald, bg: 'rgba(16,185,129,0.08)', help: 'Servers that answered a status query on the last check.' });
    if (s.total_messages !== null) rows.push({ label: 'Messages', value: s.total_messages, icon: MessageSquare,  color: C.blue,    bg: 'rgba(59,130,246,0.08)', help: 'Total private messages sent between members.' });
    return rows;
});

// ── Insight text (registrations vs the prior week) ────────────────────────────
// Pure client-side comparison of data already on the page — no extra request.
const registrationsTrend = computed(() => {
    const points = props.stats?.registrations_last_30_days ?? [];
    if (points.length < 14) return null;

    const recent = points.slice(-7).reduce((sum, d) => sum + d.count, 0);
    const prior = points.slice(-14, -7).reduce((sum, d) => sum + d.count, 0);
    if (prior === 0) return recent > 0 ? { pct: 100, up: true } : null;

    const pct = Math.round(((recent - prior) / prior) * 100);
    return { pct: Math.abs(pct), up: pct >= 0 };
});

// ── Chart: area (registrations + logins) ─────────────────────────────────────
const areaRef = ref<HTMLElement | null>(null);
let areaChart: Highcharts.Chart | null = null;

function areaOptions(): Highcharts.Options {
    const reg  = (props.stats?.registrations_last_30_days ?? []).map(d => d.count);
    const log  = (props.stats?.logins_last_30_days ?? []);
    const cats = (props.stats?.registrations_last_30_days ?? []).map(d => {
        const [, m, day] = d.date.split('-');
        return `${day}/${m}`;
    });
    const hasLogins = log.length > 0;

    return {
        chart: { type: 'area', backgroundColor: C.bg(), height: 260, animation: { duration: 600 }, style: { fontFamily: 'inherit' } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: {
            enabled: hasLogins,
            align: 'right', verticalAlign: 'top',
            itemStyle: { color: C.label(), fontWeight: '500', fontSize: '12px' },
            itemHoverStyle: { color: dark.value ? '#fff' : '#000' },
        },
        xAxis: {
            categories: cats,
            tickInterval: 5,
            labels: { style: { color: C.label(), fontSize: '11px' } },
            lineColor: C.grid(), tickColor: 'transparent',
        },
        yAxis: {
            min: 0,
            title: { text: undefined },
            gridLineColor: C.grid(),
            labels: { style: { color: C.label(), fontSize: '11px' } },
        },
        tooltip: {
            ...tooltipStyle(),
            shared: true,
            crosshairs: true,
        },
        plotOptions: {
            area: {
                marker: { enabled: false, states: { hover: { enabled: true, radius: 4 } } },
                lineWidth: 2,
                states: { hover: { lineWidth: 2 } },
            },
        },
        series: [
            {
                type: 'area',
                name: 'Registrations',
                data: reg,
                color: C.blue,
                fillColor: {
                    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                    stops: [[0, 'rgba(59,130,246,0.25)'], [1, 'rgba(59,130,246,0)']],
                },
            },
            ...(hasLogins ? [{
                type: 'area' as const,
                name: 'Logins',
                data: log.map(d => d.count),
                color: C.violet,
                fillColor: {
                    linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                    stops: [[0, 'rgba(139,92,246,0.18)'], [1, 'rgba(139,92,246,0)']],
                },
            }] : []),
        ],
    };
}

// ── Chart: donut (role distribution) ─────────────────────────────────────────
const donutRef = ref<HTMLElement | null>(null);
let donutChart: Highcharts.Chart | null = null;

function donutOptions(): Highcharts.Options {
    const roles = props.stats?.role_distribution ?? [];
    const data = roles.map(r => ({ name: r.name, y: r.count, color: r.color }));
    const total = roles.reduce((sum, r) => sum + r.count, 0);

    return {
        chart: {
            type: 'pie', backgroundColor: C.bg(), height: 260, animation: { duration: 500 }, style: { fontFamily: 'inherit' },
            events: {
                // Highcharts leaves the donut's hole empty by default — this
                // draws the total straight into it instead of wasting the space.
                render(): void {
                    const chart = this as unknown as Highcharts.Chart & { hcCenterLabel?: Highcharts.SVGElement };
                    const series = chart.series[0] as unknown as { center?: number[] } | undefined;
                    if (!series?.center) return;

                    const x = chart.plotLeft + series.center[0];
                    const y = chart.plotTop + series.center[1];

                    if (!chart.hcCenterLabel) {
                        chart.hcCenterLabel = chart.renderer.text('', 0, 0, true)
                            .css({ pointerEvents: 'none' })
                            .add();
                    }
                    chart.hcCenterLabel.attr({
                        x, y,
                        text: `<div style="transform:translate(-50%,-50%);text-align:center;line-height:1.15">`
                            + `<div style="font-size:20px;font-weight:800;color:${dark.value ? '#f4f4f5' : '#18181b'}">${total.toLocaleString()}</div>`
                            + `<div style="font-size:10px;color:#71717a;margin-top:2px;letter-spacing:.05em;text-transform:uppercase">users</div>`
                            + `</div>`,
                    });
                },
            },
        },
        title: { text: undefined },
        credits: { enabled: false },
        tooltip: {
            ...tooltipStyle(),
            pointFormat: '<b>{point.y}</b> users ({point.percentage:.1f}%)',
        },
        legend: {
            enabled: true,
            layout: 'vertical', align: 'right', verticalAlign: 'middle',
            itemStyle: { color: C.label(), fontWeight: '500', fontSize: '12px' },
            itemHoverStyle: { color: dark.value ? '#fff' : '#000' },
        },
        plotOptions: {
            pie: {
                innerSize: '60%',
                dataLabels: {
                    enabled: true, distance: -28, format: '{point.percentage:.0f}%',
                    style: { color: '#fff', fontSize: '10px', fontWeight: '700', textOutline: 'none' },
                    filter: { property: 'percentage', operator: '>', value: 6 },
                },
                showInLegend: true,
                borderWidth: 0,
                states: { hover: { brightness: 0.08 } },
            },
        },
        series: [{ type: 'pie', name: 'Users', data }],
    };
}

// ── Chart: bar (top games) ────────────────────────────────────────────────────
const barRef = ref<HTMLElement | null>(null);
let barChart: Highcharts.Chart | null = null;

function barOptions(): Highcharts.Options {
    const games = props.stats?.top_games ?? [];
    const palette = [C.blue, C.violet, C.emerald, C.amber, C.rose, C.cyan, '#a78bfa', '#fb923c'];
    return {
        chart: { type: 'bar', backgroundColor: C.bg(), height: 260, animation: { duration: 500 }, style: { fontFamily: 'inherit' } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: {
            categories: games.map(g => g.name),
            labels: { style: { color: C.label(), fontSize: '11px' } },
            lineColor: 'transparent', tickColor: 'transparent',
        },
        yAxis: {
            min: 0, title: { text: undefined },
            gridLineColor: C.grid(),
            labels: { style: { color: C.label(), fontSize: '11px' } },
        },
        tooltip: {
            ...tooltipStyle(),
            pointFormat: '<b>{point.y}</b> servers',
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                dataLabels: { enabled: true, style: { color: C.label(), fontSize: '11px', fontWeight: '500', textOutline: 'none' } },
                colorByPoint: true,
                colors: palette,
            },
        },
        series: [{ type: 'bar', name: 'Servers', data: games.map(g => g.total) }],
    };
}

// ── Chart: column (banned vs verified) ───────────────────────────────────────
const colRef = ref<HTMLElement | null>(null);
let colChart: Highcharts.Chart | null = null;

function colOptions(): Highcharts.Options {
    const s = props.stats;
    const total = s?.total_users ?? 0;
    const verified = s?.verified_users ?? 0;
    const banned = s?.banned_users ?? 0;
    const unverified = Math.max(0, total - verified - banned);

    return {
        chart: { type: 'column', backgroundColor: C.bg(), height: 260, animation: { duration: 500 }, style: { fontFamily: 'inherit' } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: {
            enabled: true, align: 'center', verticalAlign: 'bottom',
            itemStyle: { color: C.label(), fontWeight: '500', fontSize: '12px' },
        },
        xAxis: {
            categories: ['Users'],
            labels: { enabled: false },
            lineColor: 'transparent', tickColor: 'transparent',
        },
        yAxis: {
            min: 0, title: { text: undefined },
            gridLineColor: C.grid(),
            labels: { style: { color: C.label(), fontSize: '11px' } },
        },
        tooltip: { ...tooltipStyle(), shared: true },
        plotOptions: {
            column: {
                borderRadius: 6,
                borderWidth: 0,
                groupPadding: 0.1,
                pointPadding: 0.05,
            },
        },
        series: [
            { type: 'column', name: 'Verified',   data: [verified],   color: C.emerald },
            { type: 'column', name: 'Unverified',  data: [unverified], color: C.amber   },
            { type: 'column', name: 'Banned',      data: [banned],     color: C.rose    },
        ],
    };
}

// ── Init / destroy / reactivity ───────────────────────────────────────────────
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
    if (props.stats?.registrations_last_30_days?.length) {
        areaChart?.destroy(); areaChart = mkChart(areaRef.value, areaOptions());
    }
    donutChart?.destroy(); donutChart = mkChart(donutRef.value, donutOptions());
    if (props.stats?.top_games?.length) {
        barChart?.destroy(); barChart = mkChart(barRef.value, barOptions());
    }
    colChart?.destroy();  colChart = mkChart(colRef.value, colOptions());
}

onMounted(async () => {
    await loadHC();
    rebuildAll();
});

onUnmounted(() => {
    areaChart?.destroy();
    donutChart?.destroy();
    barChart?.destroy();
    colChart?.destroy();
});

watch(dark, () => rebuildAll());

// ── Quick links ───────────────────────────────────────────────────────────────
const quickLinks = [
    { label: 'Users',      desc: 'Manage accounts, roles & bans',   route: 'admin.users.index',      icon: Users,      color: C.blue,    bg: 'rgba(59,130,246,0.08)' },
    { label: 'Extensions', desc: 'Install & configure add-ons',     route: 'admin.extensions.index', icon: Puzzle,     color: C.violet,  bg: 'rgba(139,92,246,0.08)' },
    { label: 'Themes',     desc: 'Customise the public site look',  route: 'admin.themes.index',     icon: Paintbrush, color: C.amber,   bg: 'rgba(245,158,11,0.08)' },
];
</script>

<template>
    <Head title="Dashboard" />
    <AdminLayout title="Dashboard">

        <ExtensionSlot name="admin.dashboard.top" />

        <!-- Maintenance banner -->
        <div v-if="maintenance_mode"
            class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl border border-amber-500/30 bg-amber-500/10">
            <AlertTriangle :size="15" :stroke-width="2" class="text-amber-400 shrink-0" />
            <p class="text-amber-300 text-[13px] font-semibold flex-1">
                Site is in <strong>maintenance mode</strong> — visitors see a maintenance page.
            </p>
            <button type="button" @click="toggleMaintenance"
                class="text-[12px] font-bold px-3 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/30 text-amber-300 hover:bg-amber-500/30 transition-colors">
                Disable
            </button>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-zinc-100 text-xl font-black tracking-tight">Dashboard</h1>
                <p class="text-zinc-500 text-sm mt-0.5">Platform overview — live metrics &amp; activity.</p>
            </div>
            <button v-if="!maintenance_mode" type="button" @click="toggleMaintenance"
                class="flex items-center gap-1.5 text-[12px] font-semibold px-3 py-2 rounded-lg border border-zinc-800 text-zinc-500 hover:text-amber-400 hover:border-amber-500/40 transition-colors">
                <Power :size="13" :stroke-width="2" /> Maintenance
            </button>
        </div>

        <!-- KPI grid -->
        <div v-if="kpis.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 mb-6">
            <div v-for="(k, i) in kpis" :key="k.label"
                class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] px-4 py-3.5 flex items-center gap-3.5
                       transition-[border-color,transform] duration-200 hover:border-zinc-700/70 hover:-translate-y-0.5"
                :style="{ animationDelay: `${i * 40}ms` }"
            >
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                    :style="{ backgroundColor: k.bg }">
                    <component :is="k.icon" :size="16" :stroke-width="1.8" :style="{ color: k.color }" />
                </div>
                <div class="min-w-0">
                    <p class="text-[22px] font-black leading-none" :style="{ color: k.color }">
                        {{ k.value?.toLocaleString() ?? '—' }}
                    </p>
                    <p class="text-[11px] font-medium text-zinc-500 mt-0.5 truncate flex items-center gap-1">
                        {{ k.label }}
                        <Tooltip :text="k.help"><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                    </p>
                </div>
            </div>
        </div>

        <!-- Main chart + column chart row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

            <!-- Registrations / logins area chart -->
            <div class="hc-hero-in lg:col-span-2 rounded-xl border border-zinc-800/70 bg-[#111113] p-5" style="animation-delay: 120ms">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <Activity :size="13" :stroke-width="2" class="text-blue-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                            Registrations &amp; Logins
                            <Tooltip text="New account signups vs. successful logins, one point per day."><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                        </p>
                        <p class="text-[11px] text-zinc-600">Last 30 days</p>
                    </div>
                    <span v-if="registrationsTrend" class="flex items-center gap-1 text-[11px] font-bold shrink-0"
                        :class="registrationsTrend.up ? 'text-emerald-400' : 'text-red-400'">
                        <component :is="registrationsTrend.up ? TrendingUp : TrendingDown" :size="12" :stroke-width="2.5" />
                        {{ registrationsTrend.pct }}% vs prior week
                    </span>
                </div>
                <div v-if="stats?.registrations_last_30_days?.length" ref="areaRef" />
                <div v-else class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                    <Activity :size="22" :stroke-width="1.5" class="text-zinc-700" />
                    <p class="text-zinc-500 text-[12px]">No signups in the last 30 days.</p>
                </div>
            </div>

            <!-- User breakdown column chart -->
            <div class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] p-5" style="animation-delay: 160ms">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-violet-500/10 flex items-center justify-center">
                        <BarChart2 :size="13" :stroke-width="2" class="text-violet-400" />
                    </div>
                    <div>
                        <p class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                            User Status
                            <Tooltip text="Every user split into verified, unverified, or banned."><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                        </p>
                        <p class="text-[11px] text-zinc-600">Verified / unverified / banned</p>
                    </div>
                </div>
                <div ref="colRef" />
            </div>
        </div>

        <!-- Role donut + Top games bar -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

            <!-- Role distribution donut -->
            <div class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] p-5" style="animation-delay: 200ms">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <PieChart :size="13" :stroke-width="2" class="text-amber-400" />
                    </div>
                    <div>
                        <p class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                            Roles Distribution
                            <Tooltip text="Users grouped by their primary role. Users with no role assigned are excluded."><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                        </p>
                        <p class="text-[11px] text-zinc-600">Users by primary role</p>
                    </div>
                </div>
                <div v-if="stats?.role_distribution?.length" ref="donutRef" />
                <div v-else class="flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <Shapes :size="22" :stroke-width="1.5" class="text-zinc-700" />
                    <p class="text-zinc-500 text-[12px]">No roles assigned to any user yet.</p>
                </div>
            </div>

            <!-- Top games bar -->
            <div class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] p-5" style="animation-delay: 240ms">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                        <Server :size="13" :stroke-width="2" class="text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                            Top Games
                            <Tooltip text="The games with the most registered servers on this platform."><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                        </p>
                        <p class="text-[11px] text-zinc-600">Servers per game</p>
                    </div>
                </div>
                <div v-if="stats?.top_games?.length" ref="barRef" />
                <div v-else class="flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <Gamepad2 :size="22" :stroke-width="1.5" class="text-zinc-700" />
                    <p class="text-zinc-500 text-[12px]">No games yet — add one in Servers → Games.</p>
                </div>
            </div>

        </div>

        <!-- Engagement + top clicked servers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">

            <!-- Engagement counters -->
            <div class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] p-5" style="animation-delay: 280ms">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-violet-500/10 flex items-center justify-center">
                        <Activity :size="13" :stroke-width="2" class="text-violet-400" />
                    </div>
                    <div>
                        <p class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                            Community Engagement
                            <Tooltip text="Totals from reviews, comments and follows — only counted if that feature is enabled."><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                        </p>
                        <p class="text-[11px] text-zinc-600">Totals, with the last 7 days in green</p>
                    </div>
                </div>
                <div v-if="stats?.engagement && [stats.engagement.reviews, stats.engagement.comments, stats.engagement.follows].some(v => v !== null)"
                    class="grid grid-cols-3 gap-3">
                    <div v-for="item in [
                        { label: 'Reviews',  total: stats.engagement.reviews,  week: stats.engagement.reviews_week },
                        { label: 'Comments', total: stats.engagement.comments, week: stats.engagement.comments_week },
                        { label: 'Follows',  total: stats.engagement.follows,  week: stats.engagement.follows_week },
                    ]" :key="item.label" class="rounded-lg border border-zinc-800/60 bg-zinc-900/40 p-3.5 text-center">
                        <p class="text-[22px] font-black text-zinc-100 tabular-nums leading-none">{{ item.total ?? '—' }}</p>
                        <p class="text-[10px] uppercase tracking-widest text-zinc-600 mt-1.5">{{ item.label }}</p>
                        <p v-if="item.week" class="text-[11px] font-semibold text-emerald-400 mt-1">+{{ item.week }} this week</p>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <Activity :size="22" :stroke-width="1.5" class="text-zinc-700" />
                    <p class="text-zinc-500 text-[12px]">No engagement data — reviews, comments and follows aren't enabled yet.</p>
                </div>
            </div>

            <!-- Most clicked servers -->
            <div class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] p-5" style="animation-delay: 320ms">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <MousePointerClick :size="13" :stroke-width="2" class="text-amber-400" />
                    </div>
                    <div>
                        <p class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                            Most Clicked Servers
                            <Tooltip text="Servers ranked by how many times visitors clicked 'Connect' on the public site."><Info :size="12" :stroke-width="2" class="text-zinc-600 hover:text-zinc-300 transition-colors" /></Tooltip>
                        </p>
                        <p class="text-[11px] text-zinc-600">Connect clicks, last 30 days</p>
                    </div>
                </div>
                <div v-if="stats?.top_clicked_servers?.length" class="flex flex-col gap-2">
                    <div v-for="(srv, i) in stats.top_clicked_servers" :key="srv.name"
                        class="flex items-center gap-3">
                        <span class="text-[11px] font-black tabular-nums w-5 text-zinc-600">{{ i + 1 }}.</span>
                        <span class="text-[13px] text-zinc-300 truncate flex-1">{{ srv.name }}</span>
                        <div class="w-24 h-1.5 rounded-full bg-zinc-800 overflow-hidden shrink-0">
                            <div class="h-full rounded-full bg-amber-400"
                                :style="{ width: Math.round((srv.clicks / stats.top_clicked_servers[0].clicks) * 100) + '%' }" />
                        </div>
                        <span class="text-[12px] font-bold tabular-nums text-zinc-400 w-10 text-right shrink-0">{{ srv.clicks }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <MousePointerClick :size="22" :stroke-width="1.5" class="text-zinc-700" />
                    <p class="text-zinc-500 text-[12px]">No connect clicks recorded yet.</p>
                </div>
            </div>
        </div>

        <!-- Quick links -->
        <div class="hc-hero-in mb-2" style="animation-delay: 360ms">
            <p class="text-zinc-400 text-[11px] uppercase tracking-widest font-bold mb-2">Quick links</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Link v-for="link in quickLinks" :key="link.route" :href="route(link.route)"
                    class="group flex items-center gap-3 rounded-xl border border-zinc-800/70 bg-[#111113] p-4 hover:border-zinc-700/70 transition-colors">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: link.bg }">
                        <component :is="link.icon" :size="16" :stroke-width="1.75" :style="{ color: link.color }" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-zinc-100 text-[13px] font-semibold">{{ link.label }}</p>
                        <p class="text-zinc-600 text-[11px] truncate">{{ link.desc }}</p>
                    </div>
                    <ArrowRight :size="14" :stroke-width="2" class="text-zinc-700 group-hover:text-blue-400 group-hover:translate-x-0.5 transition-all shrink-0" />
                </Link>
            </div>
        </div>

        <ExtensionSlot name="admin.dashboard.bottom" />

    </AdminLayout>
</template>
