<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Wallet, Undo2, ShoppingBag, Users, TrendingUp, TrendingDown, Repeat, Download } from '@lucide/vue';
import type Highcharts from 'highcharts';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface Row { name: string; revenue: number; count: number }
interface Report {
    currency: string;
    currencies: string[];
    totals: { net: number; gross: number; refunded: number; orders: number; average: number; customers: number; previous_net: number };
    series: { label: string; net: number; refunded: number }[];
    by_gateway: Row[];
    by_source: Row[];
    top_items: Row[];
    top_customers: Row[];
    subscriptions: { active: number; mrr: number; past_due: number; started: number; canceled: number };
}

const props = defineProps<{ report: Report; filters: { range: string; from: string; to: string } }>();

const RANGES = [
    { key: '30d', label: '30 days' },
    { key: '90d', label: '90 days' },
    { key: '12m', label: '12 months' },
    { key: 'ytd', label: 'This year' },
    { key: 'custom', label: 'Custom' },
];

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function apply(params: Record<string, string>) {
    router.get(route('admin.finance.index'), {
        range: props.filters.range, currency: props.report.currency, ...params,
        ...(params.range === 'custom' || (!params.range && props.filters.range === 'custom') ? { from: from.value, to: to.value } : {}),
    }, { preserveScroll: true, preserveState: true });
}

const money = (minor: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: props.report.currency }).format(minor / 100);

const change = computed(() => {
    const { net, previous_net } = props.report.totals;
    if (!previous_net) return null;
    const pct = Math.round(((net - previous_net) / previous_net) * 100);
    return { pct: Math.abs(pct), up: pct >= 0 };
});

const kpis = computed(() => [
    { icon: Wallet, label: 'Net revenue', value: money(props.report.totals.net), help: 'Paid payments, after refunds.' },
    { icon: Undo2, label: 'Refunded', value: money(props.report.totals.refunded), help: `${money(props.report.totals.gross)} before refunds.` },
    { icon: ShoppingBag, label: 'Orders', value: props.report.totals.orders.toLocaleString(), help: `Average ${money(props.report.totals.average)}` },
    { icon: Users, label: 'Paying members', value: props.report.totals.customers.toLocaleString(), help: 'Different members who paid.' },
    { icon: Repeat, label: 'MRR', value: money(props.report.subscriptions.mrr), help: `${props.report.subscriptions.active} active subscription(s)` },
]);

const exportUrl = computed(() => `${route('admin.payments.export')}?${new URLSearchParams({ status: 'paid', from: props.filters.from, to: props.filters.to })}`);

const share = (row: Row, rows: Row[]) => {
    const total = rows.reduce((sum, r) => sum + r.revenue, 0);
    return total ? Math.round((row.revenue / total) * 100) : 0;
};

// ── Chart ─────────────────────────────────────────────────────────────────────
const dark = ref(document.documentElement.classList.contains('dark'));
const observer = new MutationObserver(() => (dark.value = document.documentElement.classList.contains('dark')));

const chartRef = ref<HTMLElement | null>(null);
let chart: Highcharts.Chart | null = null;
let HC: typeof Highcharts | null = null;

function options(): Highcharts.Options {
    const label = dark.value ? '#71717a' : '#9ca3af';
    const grid = dark.value ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)';
    const fmt = (v: number) => money(v);
    return {
        chart: { type: 'column', backgroundColor: 'transparent', height: 280, style: { fontFamily: 'inherit' } },
        title: { text: undefined },
        credits: { enabled: false },
        legend: { align: 'right', verticalAlign: 'top', itemStyle: { color: label, fontWeight: '500', fontSize: '12px' } },
        xAxis: { categories: props.report.series.map((p) => p.label), labels: { style: { color: label, fontSize: '11px' } }, lineColor: grid, tickColor: 'transparent' },
        yAxis: { min: 0, title: { text: undefined }, gridLineColor: grid, labels: { style: { color: label, fontSize: '11px' }, formatter() { return fmt(Number(this.value)); } } },
        tooltip: {
            shared: true,
            backgroundColor: dark.value ? '#18181b' : '#ffffff', borderColor: dark.value ? '#3f3f46' : '#e4e4e7', borderRadius: 10,
            style: { color: dark.value ? '#f4f4f5' : '#18181b', fontSize: '12px' },
            pointFormatter() { return `<span style="color:${this.color}">●</span> ${this.series.name}: <b>${fmt(Number(this.y))}</b><br/>`; },
        },
        plotOptions: { column: { borderWidth: 0, borderRadius: 3, groupPadding: 0.12, pointPadding: 0.04 } },
        series: [
            { type: 'column', name: 'Net revenue', data: props.report.series.map((p) => p.net), color: '#10b981' },
            { type: 'column', name: 'Refunded', data: props.report.series.map((p) => p.refunded), color: '#f59e0b' },
        ],
    };
}

function draw() {
    if (!HC || !chartRef.value) return;
    chart?.destroy();
    chart = HC.chart(chartRef.value, options());
}

onMounted(async () => {
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    HC = (await import('highcharts')).default;
    draw();
});
onUnmounted(() => {
    observer.disconnect();
    chart?.destroy();
});
watch([dark, () => props.report], draw);
</script>

<template>
    <Head title="Finance" />
    <AdminLayout title="Finance">
        <PageHeader title="Finance" description="Revenue, refunds and subscriptions for the selected period." />

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <div class="inline-flex rounded-lg border border-zinc-800/70 bg-admin-surface p-1">
                <button v-for="r in RANGES" :key="r.key" type="button"
                    class="px-3 py-1.5 rounded-md text-xs font-semibold transition-colors"
                    :class="filters.range === r.key ? 'bg-blue-500 text-white' : 'text-zinc-400 hover:text-zinc-100'"
                    @click="apply({ range: r.key })">{{ r.label }}</button>
            </div>
            <template v-if="filters.range === 'custom'">
                <input v-model="from" type="date" class="bg-admin-surface border border-zinc-800/70 rounded-lg px-3 py-1.5 text-sm text-zinc-100 [color-scheme:dark]" aria-label="From" />
                <span class="text-zinc-600 text-sm">–</span>
                <input v-model="to" type="date" class="bg-admin-surface border border-zinc-800/70 rounded-lg px-3 py-1.5 text-sm text-zinc-100 [color-scheme:dark]" aria-label="To" />
                <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400" @click="apply({ range: 'custom' })">Apply</button>
            </template>
            <select v-if="report.currencies.length > 1" :value="report.currency"
                class="bg-admin-surface border border-zinc-800/70 rounded-lg px-3 py-1.5 text-sm text-zinc-100" aria-label="Currency"
                @change="apply({ currency: ($event.target as HTMLSelectElement).value })">
                <option v-for="c in report.currencies" :key="c" :value="c">{{ c }}</option>
            </select>
            <a :href="exportUrl" class="ml-auto inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-zinc-800/70 text-zinc-300 hover:text-white hover:border-zinc-600 transition-colors">
                <Download :size="13" /> Export paid payments (CSV)
            </a>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div v-for="(k, i) in kpis" :key="k.label" class="bg-admin-surface border border-zinc-800/70 rounded-xl p-4">
                <p class="flex items-center gap-1.5 text-xs font-medium text-zinc-400"><component :is="k.icon" :size="13" class="text-zinc-500" /> {{ k.label }}</p>
                <p class="mt-2 text-xl font-bold text-zinc-100 tabular-nums">{{ k.value }}</p>
                <p class="mt-1 text-[11px] text-zinc-500">
                    <template v-if="i === 0 && change">
                        <span class="inline-flex items-center gap-0.5 font-semibold" :class="change.up ? 'text-emerald-400' : 'text-red-400'">
                            <component :is="change.up ? TrendingUp : TrendingDown" :size="11" /> {{ change.pct }}%
                        </span>
                        vs previous period
                    </template>
                    <template v-else>{{ k.help }}</template>
                </p>
            </div>
        </div>

        <!-- Chart -->
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 mb-4">
            <p class="text-sm font-semibold text-zinc-100 mb-2">Revenue over time</p>
            <div v-if="report.totals.gross === 0" class="py-16 text-center text-sm text-zinc-500">No payments in this period.</div>
            <div v-show="report.totals.gross > 0" ref="chartRef" />
        </div>

        <!-- Breakdowns -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div v-for="block in [
                { title: 'Top products', rows: report.top_items },
                { title: 'By source', rows: report.by_source },
                { title: 'By payment method', rows: report.by_gateway },
                { title: 'Top paying members', rows: report.top_customers },
            ]" :key="block.title" class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <p class="text-sm font-semibold text-zinc-100 mb-3">{{ block.title }}</p>
                <p v-if="!block.rows.length" class="text-xs text-zinc-500">Nothing in this period.</p>
                <ul v-else class="flex flex-col gap-2.5">
                    <li v-for="row in block.rows" :key="row.name">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="truncate text-zinc-200" :title="row.name">{{ row.name }}</span>
                            <span class="shrink-0 tabular-nums text-zinc-100 font-semibold">{{ money(row.revenue) }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="h-1.5 flex-1 rounded-full bg-zinc-800 overflow-hidden">
                                <div class="h-full rounded-full bg-emerald-500/70" :style="{ width: `${share(row, block.rows)}%` }" />
                            </div>
                            <span class="w-20 shrink-0 text-right text-[11px] text-zinc-500 tabular-nums">{{ row.count }} order{{ row.count === 1 ? '' : 's' }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Subscriptions -->
        <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
            <p class="text-sm font-semibold text-zinc-100 mb-3">Subscriptions</p>
            <dl class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <div><dt class="text-xs text-zinc-500">Active</dt><dd class="mt-1 text-lg font-bold text-zinc-100 tabular-nums">{{ report.subscriptions.active }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Monthly recurring</dt><dd class="mt-1 text-lg font-bold text-zinc-100 tabular-nums">{{ money(report.subscriptions.mrr) }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Started in period</dt><dd class="mt-1 text-lg font-bold text-zinc-100 tabular-nums">{{ report.subscriptions.started }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Canceled in period</dt><dd class="mt-1 text-lg font-bold text-zinc-100 tabular-nums">{{ report.subscriptions.canceled }}</dd></div>
                <div><dt class="text-xs text-zinc-500">Payment overdue</dt><dd class="mt-1 text-lg font-bold tabular-nums" :class="report.subscriptions.past_due ? 'text-amber-400' : 'text-zinc-100'">{{ report.subscriptions.past_due }}</dd></div>
            </dl>
        </div>
    </AdminLayout>
</template>
