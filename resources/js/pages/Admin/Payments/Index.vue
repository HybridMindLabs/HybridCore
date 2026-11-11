<script setup lang="ts">
import ListControls from '@/components/Admin/ListControls.vue';
import { confirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { Receipt, Undo2, Download, Search, X } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed, ref } from 'vue';

interface PaymentRow {
    id: number;
    description: string;
    user: { username: string } | null;
    amount: number;
    currency: string;
    status: string;
    gateway: string;
    external_id: string | null;
    created_at: string;
    invoice_url: string | null;
}
interface PageLink { url: string | null; label: string; active: boolean }
interface Paginator { data: PaymentRow[]; links: PageLink[]; total: number; per_page: number }

interface Filters { status: string; search: string; from: string; to: string; sort: string; dir: string; per_page?: string }

const props = defineProps<{
    payments: Paginator;
    filters: Filters;
    totals: { paid: number; refunded: number; failed: number };
}>();

const search = ref(props.filters.search);
const from = ref(props.filters.from);
const to = ref(props.filters.to);
const sort = ref(`${props.filters.sort}:${props.filters.dir}`);

const sortOptions = [
    { value: 'created_at:desc', label: 'Newest first' },
    { value: 'created_at:asc', label: 'Oldest first' },
    { value: 'amount:desc', label: 'Highest amount' },
    { value: 'amount:asc', label: 'Lowest amount' },
    { value: 'status:asc', label: 'Status' },
];

function params(overrides: Partial<Filters> = {}): Record<string, string> {
    const [sortBy, dir] = sort.value.split(':');
    const all: Filters = { status: props.filters.status, search: search.value, from: from.value, to: to.value, sort: sortBy, dir, per_page: new URLSearchParams(window.location.search).get('per_page') ?? '', ...overrides };
    return Object.fromEntries(Object.entries(all).filter(([k, v]) => v && !(k === 'status' && v === 'all')
        && !(k === 'sort' && v === 'created_at') && !(k === 'dir' && v === 'desc')));
}

const exportUrl = computed(() => route('admin.payments.export', params()));

function apply(overrides: Partial<Filters> = {}) {
    router.get(route('admin.payments.index'), params(overrides), { preserveState: true, replace: true });
}

function clearSearch() {
    search.value = '';
    apply();
}

const statusColors: Record<string, string> = {
    pending: 'text-amber-400 border-amber-500/30 bg-amber-500/10',
    paid: 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
    failed: 'text-red-400 border-red-500/30 bg-red-500/10',
    refunded: 'text-zinc-400 border-zinc-700 bg-zinc-800/50',
};

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.toUpperCase() }).format(amount);
}

function setStatus(status: string) {
    apply({ status });
}

async function refund(payment: PaymentRow) {
    if (!await confirm(`Refund ${formatMoney(payment.amount, payment.currency)} to ${payment.user?.username ?? 'this buyer'}?`)) return;
    router.post(route('admin.payments.refund', payment.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Payments" />
    <AdminLayout title="Payments">
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <div>
                <h1 class="text-[18px] font-black text-zinc-100">Payments</h1>
                <p class="text-[12px] text-zinc-500 mt-0.5">
                    {{ totals.paid }} paid · {{ totals.refunded }} refunded · {{ totals.failed }} failed
                </p>
            </div>
            <div class="flex items-center gap-1">
                <button v-for="s in ['all', 'paid', 'pending', 'failed', 'refunded']" :key="s" type="button"
                    class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold capitalize transition"
                    :class="filters.status === s
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'"
                    @click="setStatus(s)">{{ s }}</button>
            </div>
        </div>

        <form class="flex flex-wrap items-center gap-2 mb-4" @submit.prevent="apply()">
            <div class="relative w-full sm:w-72">
                <Search :size="14" :stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600" />
                <input v-model="search" type="text" placeholder="Search #id, username, email or gateway ref…"
                    class="w-full pl-9 pr-9 py-2 rounded-lg border border-zinc-800/70 bg-admin-bg text-sm text-zinc-100 placeholder-zinc-600 outline-none focus:border-zinc-600" />
                <button v-if="search" type="button" aria-label="Clear search" @click="clearSearch"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-600 hover:text-zinc-300 transition-colors">
                    <X :size="14" :stroke-width="2" />
                </button>
            </div>
            <label class="sr-only" for="payments-from">From</label>
            <input id="payments-from" v-model="from" type="date" class="px-3 py-2 rounded-lg border border-zinc-800/70 bg-admin-bg text-sm text-zinc-100 outline-none focus:border-zinc-600" @change="apply()" />
            <span class="text-zinc-600 text-sm">–</span>
            <label class="sr-only" for="payments-to">To</label>
            <input id="payments-to" v-model="to" type="date" class="px-3 py-2 rounded-lg border border-zinc-800/70 bg-admin-bg text-sm text-zinc-100 outline-none focus:border-zinc-600" @change="apply()" />
            <label class="sr-only" for="payments-sort">Sort</label>
            <select id="payments-sort" v-model="sort" class="px-3 py-2 rounded-lg border border-zinc-800/70 bg-admin-bg text-sm text-zinc-100 outline-none focus:border-zinc-600" @change="apply()">
                <option v-for="o in sortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <a :href="exportUrl" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-800/70 text-sm font-medium text-zinc-300 hover:text-white hover:border-zinc-600 transition-colors sm:ml-auto">
                <Download :size="14" :stroke-width="2" /> Export CSV
            </a>
        </form>

        <ListControls :per-page="payments.per_page" class="mb-3" />

        <div class="rounded-xl border border-zinc-800/70 bg-admin-surface overflow-hidden">
            <div v-if="!payments.data.length" class="flex flex-col items-center justify-center py-16 text-center">
                <Receipt :size="24" :stroke-width="1.5" class="text-zinc-700 mb-3" />
                <p class="text-[13px] text-zinc-600">No payments here.</p>
            </div>

            <div v-for="payment in payments.data" :key="payment.id"
                class="px-5 py-4 border-b border-zinc-800/50 last:border-0 hover:bg-white/[0.02]">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full border"
                                :class="statusColors[payment.status] ?? statusColors.pending">{{ payment.status }}</span>
                            <span class="text-[13px] font-semibold text-zinc-200">{{ payment.description }}</span>
                            <span class="text-[12px] text-zinc-500" v-if="payment.user">by <span class="font-semibold text-zinc-300">{{ payment.user.username }}</span></span>
                            <span class="text-[12px] text-zinc-600" v-else>guest</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1.5 text-[11px] text-zinc-600">
                            <span class="font-mono uppercase">{{ payment.gateway }}</span>
                            <span v-if="payment.external_id" class="font-mono">{{ payment.external_id }}</span>
                            <span>{{ payment.created_at }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-[14px] font-bold tabular-nums text-zinc-100">{{ formatMoney(payment.amount, payment.currency) }}</span>
                        <a v-if="payment.invoice_url" :href="payment.invoice_url"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-zinc-800 text-zinc-300 text-[11px] font-bold hover:text-white hover:border-zinc-600 transition">
                            <Download :size="11" :stroke-width="2" /> Invoice
                        </a>
                        <button v-if="payment.status === 'paid'" type="button" @click="refund(payment)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-red-500/30 bg-red-500/10 text-red-400 text-[11px] font-bold hover:bg-red-500/20 transition">
                            <Undo2 :size="11" :stroke-width="2" /> Refund
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="payments.links.length > 3" class="flex justify-center gap-1 mt-5 flex-wrap">
            <Link v-for="(link, i) in payments.links" :key="i" :href="link.url ?? '#'"
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
