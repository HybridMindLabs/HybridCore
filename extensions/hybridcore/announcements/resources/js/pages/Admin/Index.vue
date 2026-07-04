<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Megaphone, Plus, Pencil, Trash2, CheckCircle, XCircle } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

interface Announcement {
    id: number;
    title: string;
    type: string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    sort: number;
}

defineProps<{ announcements: Announcement[] }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const typeColor: Record<string, string> = {
    info:    'text-blue-400 bg-blue-500/10 border-blue-500/20',
    success: 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
    warning: 'text-amber-400 bg-amber-500/10 border-amber-500/20',
    danger:  'text-red-400 bg-red-500/10 border-red-500/20',
};

function destroy(id: number) {
    if (confirm('Delete this announcement?')) {
        router.delete(route('admin.announcements.destroy', id));
    }
}
</script>

<template>
    <Head title="Announcements" />
    <AdminLayout title="Announcements">
        <PageHeader title="Announcements" :icon="Megaphone">
            <template #actions>
                <Link
                    :href="route('admin.announcements.create')"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white text-[13px] font-bold px-4 py-2 rounded-xl transition"
                >
                    <Plus :size="14" :stroke-width="2.2" />
                    New
                </Link>
            </template>
        </PageHeader>

        <div class="rounded-xl border overflow-hidden"
            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">

            <div v-if="!announcements.length"
                class="flex flex-col items-center py-16 text-center">
                <Megaphone :size="22" :stroke-width="1.5" class="mb-2" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                <p class="text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No announcements yet.</p>
            </div>

            <table v-else class="w-full text-[13px]">
                <thead>
                    <tr class="border-b text-[10px] font-black uppercase tracking-widest"
                        :class="dark ? 'border-zinc-800/60 text-zinc-600 bg-[#1a1a1e]' : 'border-zinc-100 text-zinc-400 bg-zinc-50'">
                        <th class="px-4 py-3 text-left">Title</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Sort</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                    <tr v-for="a in announcements" :key="a.id"
                        class="transition-colors"
                        :class="dark ? 'hover:bg-white/[0.02]' : 'hover:bg-zinc-50'">
                        <td class="px-4 py-3 font-semibold max-w-[320px] truncate"
                            :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                            {{ a.title }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold border capitalize"
                                :class="typeColor[a.type]">
                                {{ a.type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold"
                                :class="a.is_active ? 'text-emerald-400' : dark ? 'text-zinc-600' : 'text-zinc-400'">
                                <component :is="a.is_active ? CheckCircle : XCircle" :size="13" :stroke-width="2" />
                                {{ a.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                            {{ a.sort }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('admin.announcements.edit', a.id)"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border transition-colors"
                                    :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-400 hover:text-zinc-700'">
                                    <Pencil :size="13" :stroke-width="1.75" />
                                </Link>
                                <button type="button" @click="destroy(a.id)"
                                    class="w-8 h-8 flex items-center justify-center rounded-lg border transition-colors"
                                    :class="dark ? 'border-zinc-800 text-zinc-600 hover:text-red-400 hover:border-red-500/30' : 'border-zinc-200 text-zinc-400 hover:text-red-500'">
                                    <Trash2 :size="13" :stroke-width="1.75" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
