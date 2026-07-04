<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import EmailTabs from '@/components/Admin/Email/EmailTabs.vue';
import Pagination from '@/components/UI/Pagination.vue';
import { ScrollText, CheckCircle, XCircle, MinusCircle } from '@lucide/vue';
import { ref } from 'vue';

interface EmailLog {
    id: number;
    to: string;
    subject: string;
    template_slug: string | null;
    status: 'sent' | 'failed' | 'skipped';
    error: string | null;
    sent_at: string | null;
    created_at: string;
}

interface Paginator {
    data: EmailLog[];
    links: any;
    total: number;
    current_page: number;
    last_page: number;
}

defineProps<{ logs: Paginator }>();

const search = ref('');
const statusFilter = ref('');
const searching = ref(false);

function applyFilter() {
    router.get(route('admin.email.logs'), {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
    }, {
        preserveState: true, replace: true,
        onStart: () => { searching.value = true; },
        onFinish: () => { searching.value = false; },
    });
}

const statusIcon = { sent: CheckCircle, failed: XCircle, skipped: MinusCircle } as const;
const statusClass = { sent: 'text-emerald-400', failed: 'text-red-400', skipped: 'text-zinc-500' } as const;

const breadcrumbs = [
    { label: 'Admin', href: route('admin.dashboard') },
    { label: 'Email Settings', href: route('admin.email.settings') },
    { label: 'Logs' },
];
</script>

<template>
    <Head title="Email Logs" />
    <AdminLayout title="Email Logs">
        <Breadcrumb :items="breadcrumbs" />
        <EmailTabs active="logs" />

        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                    <ScrollText :size="14" :stroke-width="1.8" class="text-cyan-400" />
                </div>
                <div>
                    <h1 class="text-zinc-100 text-[15px] font-black">Email Logs</h1>
                    <p class="text-zinc-600 text-[11px]">{{ logs.total }} total emails</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input v-model="search" type="search" placeholder="Search by email…" @change="applyFilter"
                    class="bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-cyan-500 w-48" />
                <select v-model="statusFilter" @change="applyFilter"
                    class="bg-zinc-900 border border-zinc-800 text-zinc-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:border-cyan-500">
                    <option value="">All statuses</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                    <option value="skipped">Skipped</option>
                </select>
            </div>
        </div>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden transition-opacity"
            :class="searching ? 'opacity-50 pointer-events-none' : ''">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">To</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Subject</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Template</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Status</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!logs.data.length">
                        <td colspan="5" class="text-center text-zinc-600 px-4 py-8">No email logs yet.</td>
                    </tr>
                    <tr v-for="log in logs.data" :key="log.id"
                        class="border-b border-zinc-800/50 hover:bg-zinc-800/20 transition-colors">
                        <td class="px-4 py-3 text-zinc-300">{{ log.to }}</td>
                        <td class="px-4 py-3 text-zinc-400 max-w-xs truncate" :title="log.subject">{{ log.subject }}</td>
                        <td class="px-4 py-3 text-zinc-600 font-mono text-[11px]">{{ log.template_slug ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div :class="statusClass[log.status]" class="flex items-center gap-1">
                                <component :is="statusIcon[log.status]" :size="12" />
                                {{ log.status }}
                            </div>
                            <div v-if="log.error" class="text-red-400/70 text-[10px] mt-0.5 truncate max-w-[160px]" :title="log.error">{{ log.error }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 text-xs">{{ log.sent_at ?? log.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination v-if="logs.last_page > 1" :links="logs.links" class="mt-4" />
    </AdminLayout>
</template>
