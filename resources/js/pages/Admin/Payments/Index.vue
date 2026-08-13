<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Receipt, Undo2, Download } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

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
interface Paginator { data: PaymentRow[]; links: PageLink[]; total: number }

const props = defineProps<{
    payments: Paginator;
    filters: { status: string };
    totals: { paid: number; refunded: number; failed: number };
}>();

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
    router.get(route('admin.payments.index'), status === 'all' ? {} : { status }, { preserveState: true, replace: true });
}

function refund(payment: PaymentRow) {
    if (!confirm(`Refund ${formatMoney(payment.amount, payment.currency)} to ${payment.user?.username ?? 'this buyer'}?`)) return;
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

        <div class="rounded-xl border border-zinc-800/70 bg-[#111113] overflow-hidden">
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
