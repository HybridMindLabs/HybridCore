<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { TrendingUp } from '@lucide/vue';
import { computed } from 'vue';
import HighchartsChart from './HighchartsChart.vue';
import { revenueChart, type Revenue } from './charts';

const props = defineProps<{ revenue: Revenue; dark: boolean }>();

function money(amount: number): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: props.revenue.currency || 'USD', maximumFractionDigits: 2 }).format(amount);
}

function setRange(range: number) {
    router.get(route('admin.dashboard'), { range }, { only: ['revenue'], preserveScroll: true, preserveState: true, replace: true });
}

const figures = computed(() => [
    { label: 'Today', value: money(props.revenue.today), warn: false },
    { label: 'This month', value: money(props.revenue.month), warn: false },
    { label: `Last ${props.revenue.range} days`, value: money(props.revenue.period), warn: false },
    { label: 'Average order', value: money(props.revenue.average), warn: false },
    { label: 'Failed', value: props.revenue.failed_count.toLocaleString(), warn: props.revenue.failed_count > 0 },
    { label: 'Refunded', value: `${props.revenue.refunded_count} · ${money(props.revenue.refunded_amount)}`, warn: props.revenue.refunded_count > 0 },
]);

const options = computed(() => revenueChart(props.revenue, props.dark, money));
</script>

<template>
    <section class="hc-hero-in rounded-xl border border-zinc-800/70 bg-admin-surface p-5 mb-4" style="animation-delay: 80ms" aria-label="Revenue">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 flex items-center justify-center" aria-hidden="true">
                <TrendingUp :size="13" :stroke-width="2" class="text-emerald-400" />
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-[13px] font-black text-zinc-100">Revenue</h2>
                <p class="text-[11px] text-zinc-500">Paid payments in {{ revenue.currency }}</p>
            </div>
            <div class="flex items-center gap-1" role="group" aria-label="Revenue period">
                <button v-for="r in [7, 30, 90]" :key="r" type="button"
                    class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors"
                    :class="revenue.range === r ? 'bg-emerald-500/15 text-emerald-300' : 'text-zinc-500 hover:text-zinc-200 hover:bg-admin-elevated'"
                    :aria-pressed="revenue.range === r"
                    @click="setRange(r)">{{ r }}d</button>
            </div>
            <Link :href="route('admin.payments.index')" class="text-xs text-zinc-500 hover:text-zinc-200 ml-1">View payments →</Link>
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <div v-for="m in figures" :key="m.label" class="rounded-lg bg-admin-sunken border border-zinc-800/60 px-3 py-2.5">
                <dt class="text-[11px] text-zinc-500">{{ m.label }}</dt>
                <dd class="text-[15px] font-bold tabular-nums mt-0.5" :class="m.warn ? 'text-amber-300' : 'text-zinc-100'">{{ m.value }}</dd>
            </div>
        </dl>

        <HighchartsChart v-if="revenue.period > 0" :options="options" label="Revenue per day" />
        <p v-else class="py-10 text-center text-[12px] text-zinc-500">No paid payments in the last {{ revenue.range }} days.</p>
    </section>
</template>
