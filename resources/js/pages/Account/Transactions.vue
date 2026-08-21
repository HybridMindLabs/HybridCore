<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Receipt, Download, RefreshCw, XCircle, Wallet, RotateCw, ListOrdered } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import AccountPage from '@/components/Account/AccountPage.vue';
import { computed, ref } from 'vue';

interface PaymentRow {
    id: number;
    description: string;
    amount: number;
    currency: string;
    status: string;
    gateway: string;
    created_at: string;
    invoice_url: string | null;
    items_url: string | null;
}

interface SubscriptionRow {
    id: number;
    description: string;
    amount: number;
    currency: string;
    interval: string;
    status: string;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
}

interface PaginatedPayments {
    data: PaymentRow[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    payments: PaginatedPayments;
    subscriptions: SubscriptionRow[];
    totalSpent: number;
    totalSpentCurrency: string;
    unreadNotifications?: number;
    unreadMessages?: number;
}>();

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');
const DATE_FORMAT: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' };

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-500/10 text-amber-500',
    paid: 'bg-emerald-500/10 text-emerald-500',
    failed: 'bg-red-500/10 text-red-500',
    refunded: 'bg-zinc-500/15 text-zinc-400',
    incomplete: 'bg-amber-500/10 text-amber-500',
    active: 'bg-emerald-500/10 text-emerald-500',
    past_due: 'bg-amber-500/10 text-amber-500',
    canceled: 'bg-zinc-500/15 text-zinc-400',
};

function paymentStatusLabel(status: string): string {
    return t(`account.tx_status_${status}`);
}

function subStatusLabel(status: string): string {
    return t(`account.tx_sub_status_${status}`);
}

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency.toUpperCase() }).format(amount);
}

// Small at-a-glance header — the page previously jumped straight into two
// bare lists with nothing orienting the reader first.
const activeSubCount = computed(() =>
    props.subscriptions.filter((s) => s.status === 'active' || s.status === 'past_due').length);

const canceling = ref<number | null>(null);

function cancelSubscription(subscription: SubscriptionRow) {
    if (!confirm(t('account.tx_cancel_confirm'))) return;

    canceling.value = subscription.id;
    router.post(route('account.subscriptions.cancel', subscription.id), {}, {
        preserveScroll: true,
        onFinish: () => { canceling.value = null; },
    });
}
</script>

<template>
    <Head :title="t('account.tab_transactions')" />

    <AccountPage
        active-tab="transactions"
        :section="t('account.tab_transactions')"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
    >
        <template #subtitle>{{ t('account.tx_subtitle') }}</template>

        <!-- Stat header — orients before the two lists, real numbers only. -->
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="rounded-2xl border p-4 flex items-center gap-3"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :class="dark ? 'bg-emerald-500/10 text-emerald-400' : 'bg-emerald-50 text-emerald-600'">
                    <Wallet :size="17" :stroke-width="2" />
                </span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.tx_total_spent') }}</p>
                    <p class="text-[16px] font-black tabular-nums truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ formatMoney(totalSpent, totalSpentCurrency) }}</p>
                </div>
            </div>
            <div class="rounded-2xl border p-4 flex items-center gap-3"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" :class="dark ? 'bg-violet-500/10 text-violet-400' : 'bg-violet-50 text-violet-600'">
                    <RotateCw :size="17" :stroke-width="2" />
                </span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wide" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.tx_active_subs') }}</p>
                    <p class="text-[16px] font-black tabular-nums truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ activeSubCount }}</p>
                </div>
            </div>
        </div>

        <!-- Subscriptions -->
        <div class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
            <div class="px-5 sm:px-6 py-4 border-b"
                :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.tx_subscriptions') }}
                </h2>
            </div>

            <div v-if="subscriptions.length === 0" class="flex flex-col items-center text-center px-6 py-12">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <RefreshCw :size="24" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                    {{ t('account.tx_no_subscriptions') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.tx_no_subscriptions_hint') }}
                </p>
            </div>

            <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
                :aria-label="t('account.tx_sub_list_label')">
                <li v-for="s in subscriptions" :key="s.id"
                    class="flex items-center gap-4 px-5 py-4 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <p class="text-[13.5px] font-bold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ s.description }}
                        </p>
                        <div class="flex items-center gap-3 mt-1 flex-wrap text-[11.5px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            <span class="font-semibold tabular-nums">
                                {{ t('account.tx_per_interval', { amount: formatMoney(s.amount, s.currency), interval: s.interval }) }}
                            </span>
                            <span v-if="s.current_period_end">
                                {{ t(s.cancel_at_period_end ? 'account.tx_ends_on' : 'account.tx_renews_on', { date: formatDate(s.current_period_end, DATE_FORMAT) }) }}
                            </span>
                        </div>
                    </div>

                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold shrink-0" :class="statusStyles[s.status]">
                        {{ subStatusLabel(s.status) }}
                    </span>

                    <span v-if="s.cancel_at_period_end" class="text-[11.5px] font-semibold shrink-0" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ t('account.tx_cancel_pending') }}
                    </span>
                    <button v-else-if="s.status === 'active' || s.status === 'past_due'" type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11.5px] font-bold transition disabled:opacity-50 shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50"
                        :class="dark ? 'border-zinc-800 text-zinc-400 hover:text-red-400 hover:border-red-500/40' : 'border-zinc-300 text-zinc-500 hover:text-red-600 hover:border-red-300'"
                        :disabled="canceling === s.id"
                        @click="cancelSubscription(s)">
                        <XCircle :size="12" :stroke-width="2" aria-hidden="true" />
                        {{ t('account.tx_cancel') }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Payments -->
        <div class="rounded-2xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
            <div class="px-5 sm:px-6 py-4 border-b"
                :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                <h2 class="text-[14px] font-black" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.tx_payments') }}
                </h2>
            </div>

            <div v-if="payments.data.length === 0" class="flex flex-col items-center text-center px-6 py-14">
                <span class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                    :class="dark ? 'bg-zinc-900 text-zinc-500' : 'bg-zinc-100 text-zinc-400'" aria-hidden="true">
                    <Receipt :size="26" :stroke-width="1.4" />
                </span>
                <p class="text-[15px] font-bold" :class="dark ? 'text-zinc-300' : 'text-zinc-500'">
                    {{ t('account.tx_no_payments') }}
                </p>
                <p class="text-[13px] mt-1 max-w-sm" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.tx_no_payments_hint') }}
                </p>
            </div>

            <ul v-else class="divide-y" :class="dark ? 'divide-zinc-800/60' : 'divide-zinc-200'"
                :aria-label="t('account.tx_list_label')">
                <li v-for="p in payments.data" :key="p.id"
                    class="flex items-center gap-4 px-5 py-4 flex-wrap"
                    :class="dark ? 'hover:bg-white/[0.02]' : 'hover:bg-zinc-100/60'">
                    <div class="flex-1 min-w-0">
                        <p class="text-[13.5px] font-bold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ p.description }}
                        </p>
                        <div class="flex items-center gap-3 mt-1 flex-wrap text-[11.5px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            <span>{{ formatDate(p.created_at, DATE_FORMAT) }}</span>
                            <span class="capitalize">{{ p.gateway }}</span>
                        </div>
                    </div>

                    <span class="text-[13px] font-bold tabular-nums shrink-0" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                        {{ formatMoney(p.amount, p.currency) }}
                    </span>

                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold shrink-0" :class="statusStyles[p.status]">
                        {{ paymentStatusLabel(p.status) }}
                    </span>

                    <Link v-if="p.items_url" :href="p.items_url"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11.5px] font-bold transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600' : 'border-zinc-300 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400'">
                        <ListOrdered :size="12" :stroke-width="2" aria-hidden="true" />
                        {{ t('account.tx_view_items') }}
                    </Link>

                    <a v-if="p.invoice_url" :href="p.invoice_url"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11.5px] font-bold transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                        :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600' : 'border-zinc-300 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400'"
                        :aria-label="t('account.tx_download_invoice')">
                        <Download :size="12" :stroke-width="2" aria-hidden="true" />
                        {{ t('account.tx_download_invoice') }}
                    </a>
                </li>
            </ul>

            <div v-if="payments.links.length > 3" class="flex justify-center gap-1 px-5 py-4 flex-wrap border-t"
                :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
                <component :is="link.url ? Link : 'span'" v-for="(link, i) in payments.links" :key="i" :href="link.url ?? undefined"
                    class="px-3 py-1.5 rounded-lg border text-[12px] font-semibold transition"
                    :aria-disabled="!link.url ? 'true' : undefined"
                    :aria-current="link.active ? 'page' : undefined"
                    :class="link.active
                        ? (dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-300 bg-blue-50 text-blue-700')
                        : link.url
                            ? (dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-900 hover:border-zinc-400')
                            : (dark ? 'border-zinc-800/40 text-zinc-800' : 'border-zinc-100 text-zinc-300')"
                    v-html="link.label" />
            </div>
        </div>
    </AccountPage>
</template>
