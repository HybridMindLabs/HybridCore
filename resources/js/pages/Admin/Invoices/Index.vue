<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Receipt, Download, Search, FileText, Wallet, CalendarClock, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface InvoiceRow {
    id: number;
    number: string;
    user: string;
    amount: number;
    currency: string;
    issued_at: string;
    download_url: string;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: InvoiceRow[]; links: PageLink[]; total: number }
interface Stats { total: number; totalAmount: number; monthCount: number; monthAmount: number; currency: string }

const props = defineProps<{ invoices: Paginator; filters: { q: string }; stats: Stats }>();

const search = ref(props.filters.q);

function submitSearch() {
    router.get(route('admin.invoices.index'), search.value ? { q: search.value } : {}, { preserveState: true, replace: true });
}

function clearSearch() {
    search.value = '';
    submitSearch();
}

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.toUpperCase() }).format(amount);
}

const kpis = computed(() => [
    { label: 'Total invoices', value: props.stats.total.toLocaleString(), icon: FileText, color: '#3b82f6', bg: 'rgba(59,130,246,0.08)' },
    { label: 'Total billed', value: money(props.stats.totalAmount, props.stats.currency), icon: Wallet, color: '#10b981', bg: 'rgba(16,185,129,0.08)' },
    { label: 'This month', value: `${props.stats.monthCount.toLocaleString()} · ${money(props.stats.monthAmount, props.stats.currency)}`, icon: CalendarClock, color: '#8b5cf6', bg: 'rgba(139,92,246,0.08)' },
]);
</script>

<template>
    <Head title="Invoices" />
    <AdminLayout title="Invoices">
        <PageHeader title="Invoices" description="One generated automatically for every payment the moment it's marked paid." :icon="Receipt" />

        <!-- KPI strip — same tile language as the Shop dashboard, so this
             reads as part of the same admin, not a bolted-on afterthought. -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            <div v-for="(k, i) in kpis" :key="k.label"
                class="hc-hero-in rounded-xl border border-zinc-800/70 bg-[#111113] px-4 py-3.5 flex items-center gap-3.5 transition-[border-color,transform] duration-200 hover:border-zinc-700/70 hover:-translate-y-0.5"
                :style="{ animationDelay: `${i * 40}ms` }">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" :style="{ backgroundColor: k.bg }">
                    <component :is="k.icon" :size="16" :stroke-width="1.8" :style="{ color: k.color }" />
                </div>
                <div class="min-w-0">
                    <p class="text-[17px] font-black leading-none truncate" :style="{ color: k.color }">{{ k.value }}</p>
                    <p class="text-[11px] font-medium text-zinc-500 mt-1 truncate">{{ k.label }}</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submitSearch" class="relative mb-4 max-w-sm">
            <Search :size="14" :stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600" />
            <input v-model="search" type="text" placeholder="Search invoice # or username…"
                class="w-full pl-9 pr-9 py-2 rounded-lg border border-zinc-800/70 bg-[#09090b] text-sm text-zinc-100 placeholder-zinc-600 outline-none focus:border-zinc-600" />
            <button v-if="search" type="button" @click="clearSearch"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-600 hover:text-zinc-300 transition-colors">
                <X :size="14" :stroke-width="2" />
            </button>
        </form>

        <div class="hc-hero-in rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden" style="animation-delay: 100ms">
            <div v-if="!invoices.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                <Receipt :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                <p class="text-[13px] text-zinc-600">{{ filters.q ? `No invoices match "${filters.q}".` : 'No invoices yet — one is generated automatically the moment a payment is marked paid.' }}</p>
            </div>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70 bg-[#17171a] text-zinc-500 text-[11px] font-bold uppercase tracking-wide">
                        <th class="text-left px-4 py-3">Invoice</th>
                        <th class="text-left px-4 py-3">Member</th>
                        <th class="text-left px-4 py-3">Amount</th>
                        <th class="text-left px-4 py-3">Issued</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="invoice in invoices.data" :key="invoice.id" class="border-b border-zinc-800/50 last:border-0 transition-colors hover:bg-white/[0.02]">
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-blue-500/10">
                                    <FileText :size="14" :stroke-width="1.8" class="text-blue-400" />
                                </span>
                                <span class="font-mono font-semibold text-zinc-200">{{ invoice.number }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-zinc-400">{{ invoice.user }}</td>
                        <td class="px-4 py-3.5 font-bold tabular-nums text-zinc-100">{{ money(invoice.amount, invoice.currency) }}</td>
                        <td class="px-4 py-3.5 text-zinc-500">{{ invoice.issued_at }}</td>
                        <td class="px-4 py-3.5 text-right">
                            <a :href="invoice.download_url"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-zinc-800 text-zinc-300 text-[11px] font-bold hover:text-white hover:border-zinc-600 hover:bg-white/[0.06] transition-colors">
                                <Download :size="11" :stroke-width="2" /> Download
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="invoices.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
            <Link v-for="(link, i) in invoices.links" :key="i" :href="link.url ?? '#'"
                class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold transition"
                :class="link.active
                    ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                    : link.url
                        ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'
                        : 'border-zinc-800/40 text-zinc-800 pointer-events-none'"
                v-html="link.label" />
        </div>
    </AdminLayout>
</template>
