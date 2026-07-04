<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import Pagination from '@/components/UI/Pagination.vue';
import { ShieldBan, Trash2, Plus, X } from '@lucide/vue';
import { ref } from 'vue';

interface BannedBy { id: number; name: string }
interface IpBan { id: number; ip: string; reason: string | null; banned_by: BannedBy | null; expires_at: string | null; created_at: string }
interface Paginator { data: IpBan[]; links: any; current_page: number; last_page: number; total: number; per_page: number; from: number | null; to: number | null }

defineProps<{ bans: Paginator }>();

const showForm = ref(false);
const form = useForm({ ip: '', reason: '', expires_at: '' });

function submit() {
    form.post(route('admin.ip-bans.store'), {
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
}
function remove(id: number) {
    if (!confirm('Remove this IP ban?')) return;
    router.delete(route('admin.ip-bans.destroy', id));
}

const breadcrumbs = [{ label: 'Admin', href: route('admin.dashboard') }, { label: 'IP Bans' }];
</script>

<template>
    <Head title="IP Bans" />
    <AdminLayout title="IP Bans">
        <Breadcrumb :items="breadcrumbs" />

        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
                    <ShieldBan :size="14" :stroke-width="1.8" class="text-red-400" />
                </div>
                <div>
                    <h1 class="text-zinc-100 text-[15px] font-black">IP Bans</h1>
                    <p class="text-zinc-600 text-[11px]">{{ bans.total }} banned IP addresses</p>
                </div>
            </div>
            <button type="button" @click="showForm = !showForm"
                class="flex items-center gap-1.5 bg-red-500/20 border border-red-500/30 text-red-300 text-sm font-semibold rounded-lg px-3 py-2 hover:bg-red-500/30 transition-colors">
                <component :is="showForm ? X : Plus" :size="13" :stroke-width="2" />
                {{ showForm ? 'Cancel' : 'Ban IP' }}
            </button>
        </div>

        <!-- Add form -->
        <Transition name="slide">
            <form v-if="showForm" @submit.prevent="submit"
                class="mb-4 p-4 bg-[#111113] border border-zinc-800/70 rounded-xl flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[140px]">
                    <label class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1 block">IP Address <span class="text-zinc-700 normal-case">(or CIDR range)</span></label>
                    <input v-model="form.ip" type="text" placeholder="192.168.1.1 or 10.0.0.0/8"
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-500" required />
                    <p v-if="form.errors.ip" class="text-red-400 text-[11px] mt-1">{{ form.errors.ip }}</p>
                </div>
                <div class="flex-1 min-w-[140px]">
                    <label class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1 block">Reason</label>
                    <input v-model="form.reason" type="text" placeholder="Spam / abuse…"
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-500" />
                </div>
                <div class="flex-1 min-w-[140px]">
                    <label class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-1 block">Expires at <span class="text-zinc-700">(optional)</span></label>
                    <input v-model="form.expires_at" type="datetime-local"
                        class="w-full bg-zinc-900 border border-zinc-800 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-500" />
                </div>
                <button type="submit" :disabled="form.processing"
                    class="px-4 py-2 bg-red-500 text-white font-bold text-sm rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50">
                    Ban IP
                </button>
            </form>
        </Transition>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div v-if="bans.data.length === 0" class="p-16 text-center">
                <ShieldBan :size="28" :stroke-width="1.5" class="mx-auto mb-3 text-zinc-700" />
                <p class="text-zinc-500 text-sm font-semibold">No IP bans yet</p>
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70">
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3">IP Address</th>
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3 hidden sm:table-cell">Reason</th>
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3 hidden md:table-cell">Banned by</th>
                        <th class="text-left text-zinc-600 text-xs font-medium uppercase tracking-wide px-4 py-3 hidden lg:table-cell">Expires</th>
                        <th class="px-4 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ban in bans.data" :key="ban.id"
                        class="border-b border-zinc-800/50 hover:bg-zinc-900/40 transition-colors last:border-0">
                        <td class="px-4 py-3">
                            <span class="font-mono text-[13px] text-red-300">{{ ban.ip }}</span>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell text-zinc-500 text-[12px]">{{ ban.reason ?? '—' }}</td>
                        <td class="px-4 py-3 hidden md:table-cell text-zinc-500 text-[12px]">{{ ban.banned_by?.name ?? 'System' }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell text-zinc-500 text-[11px]">{{ ban.expires_at ?? 'Never' }}</td>
                        <td class="px-4 py-3">
                            <button type="button" class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-500/10 transition-colors" @click="remove(ban.id)">
                                <Trash2 :size="13" :stroke-width="1.75" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :paginator="bans" />
        </div>
    </AdminLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
