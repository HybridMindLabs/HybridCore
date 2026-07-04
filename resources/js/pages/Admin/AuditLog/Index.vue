<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import Pagination from '@/components/UI/Pagination.vue';
import { Shield, Search, X, User, Trash2, Pencil, ShieldBan, RefreshCw } from '@lucide/vue';
import { ref, watch } from 'vue';

interface Actor { id: number; name: string; email: string }
interface LogEntry {
    id: number; action: string; description: string | null;
    subject_type: string | null; subject_id: number | null;
    causer: Actor | null; properties: Record<string, unknown>;
    created_at: string;
}
interface Paginator { data: LogEntry[]; links: any; current_page: number; last_page: number; total: number; per_page: number; from: number | null; to: number | null }

defineProps<{ logs: Paginator; filters: { search: string; action: string } }>();

const search = ref('');
const action = ref('');
const searching = ref(false);
let t: ReturnType<typeof setTimeout> | null = null;
watch([search, action], () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => {
        router.get(route('admin.activity-log.index'), { search: search.value, action: action.value }, {
            preserveState: true, replace: true,
            onStart: () => { searching.value = true; },
            onFinish: () => { searching.value = false; },
        });
    }, 350);
});

const actionIcons: Record<string, unknown> = {
    created: Pencil, updated: RefreshCw, deleted: Trash2,
    banned: ShieldBan, unbanned: Shield, verified: Shield,
};
const actionColors: Record<string, string> = {
    created: 'text-emerald-400', updated: 'text-blue-400', deleted: 'text-red-400',
    banned: 'text-rose-400', unbanned: 'text-emerald-400', verified: 'text-blue-400',
};
function iconFor(a: string) { return actionIcons[a.toLowerCase()] ?? RefreshCw; }
function colorFor(a: string) { return actionColors[a.toLowerCase()] ?? 'text-zinc-400'; }

const breadcrumbs = [{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Audit Log' }];
</script>

<template>
    <Head title="Audit Log" />
    <AdminLayout title="Audit Log">
        <Breadcrumb :items="breadcrumbs" />

        <div class="flex items-center gap-2 mb-5">
            <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center">
                <Shield :size="14" :stroke-width="1.8" class="text-violet-400" />
            </div>
            <div>
                <h1 class="text-zinc-100 text-[15px] font-black">Audit Log</h1>
                <p class="text-zinc-600 text-[11px]">All admin actions across the platform — {{ logs.total }} total entries.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-3 mb-4 flex-wrap">
            <div class="relative max-w-xs flex-1 min-w-[200px]">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600" />
                <input v-model="search" type="text" placeholder="Search actions or descriptions…"
                    class="w-full bg-[#111113] border border-zinc-800/70 text-zinc-100 rounded-lg pl-9 pr-3 py-2 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500" />
            </div>
            <select v-model="action"
                class="bg-[#111113] border border-zinc-800/70 text-zinc-400 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                <option value="">All actions</option>
                <option value="created">Created</option>
                <option value="updated">Updated</option>
                <option value="deleted">Deleted</option>
                <option value="banned">Banned</option>
                <option value="unbanned">Unbanned</option>
                <option value="verified">Verified</option>
            </select>
        </div>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden transition-opacity"
            :class="searching ? 'opacity-50 pointer-events-none' : ''">
            <div v-if="logs.data.length === 0" class="p-16 text-center">
                <Shield :size="28" :stroke-width="1.5" class="mx-auto mb-3 text-zinc-700" />
                <p class="text-zinc-500 text-sm font-semibold">No audit log entries found</p>
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70">
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3">Action</th>
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3 hidden md:table-cell">Actor</th>
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3 hidden lg:table-cell">Target</th>
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3">When</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id"
                        class="border-b border-zinc-800/50 hover:bg-zinc-900/40 transition-colors last:border-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <component :is="iconFor(log.action)" :size="13" :stroke-width="1.8" :class="colorFor(log.action)" />
                                <div>
                                    <p class="text-zinc-200 font-semibold text-[12px] capitalize">{{ log.action }}</p>
                                    <p v-if="log.description" class="text-zinc-600 text-[11px] truncate max-w-[220px]">{{ log.description }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            <div v-if="log.causer" class="flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center shrink-0">
                                    <User :size="9" :stroke-width="2" class="text-zinc-500" />
                                </div>
                                <span class="text-zinc-400 text-[12px]">{{ log.causer.name }}</span>
                            </div>
                            <span v-else class="text-zinc-700 text-[11px]">System</span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <span v-if="log.subject_type" class="text-zinc-600 text-[11px] font-mono">
                                {{ log.subject_type.split('\\').pop() }} #{{ log.subject_id }}
                            </span>
                            <span v-else class="text-zinc-700 text-[11px]">—</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 text-[11px]">{{ log.created_at }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :paginator="logs" />
        </div>
    </AdminLayout>
</template>
