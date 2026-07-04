<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import EmailTabs from '@/components/Admin/Email/EmailTabs.vue';
import { FileText, Pencil, CheckCircle, XCircle } from '@lucide/vue';

interface EmailTemplate {
    id: number;
    slug: string;
    name: string;
    subject: string;
    active: boolean;
    system: boolean;
    updated_at: string;
}

defineProps<{ templates: EmailTemplate[] }>();

const breadcrumbs = [
    { label: 'Admin', href: route('admin.dashboard') },
    { label: 'Email Settings', href: route('admin.email.settings') },
    { label: 'Templates' },
];
</script>

<template>
    <Head title="Email Templates" />
    <AdminLayout title="Email Templates">
        <Breadcrumb :items="breadcrumbs" />
        <EmailTabs active="templates" />

        <div class="flex items-center gap-2 mb-5">
            <div class="w-8 h-8 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                <FileText :size="14" :stroke-width="1.8" class="text-cyan-400" />
            </div>
            <div>
                <h1 class="text-zinc-100 text-[15px] font-black">Email Templates</h1>
                <p class="text-zinc-600 text-[11px]">{{ templates.length }} templates</p>
            </div>
        </div>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Name</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Slug</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Subject</th>
                        <th class="text-left text-zinc-500 text-[11px] font-semibold uppercase tracking-wide px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tpl in templates" :key="tpl.id"
                        class="border-b border-zinc-800/50 hover:bg-zinc-800/20 transition-colors">
                        <td class="px-4 py-3">
                            <div class="text-zinc-200 font-semibold">{{ tpl.name }}</div>
                            <div v-if="tpl.system" class="text-zinc-600 text-[10px]">system</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-500 font-mono text-[12px]">{{ tpl.slug }}</td>
                        <td class="px-4 py-3 text-zinc-400 max-w-xs truncate">{{ tpl.subject }}</td>
                        <td class="px-4 py-3">
                            <span :class="tpl.active ? 'text-emerald-400' : 'text-zinc-600'" class="flex items-center gap-1">
                                <component :is="tpl.active ? CheckCircle : XCircle" :size="13" />
                                {{ tpl.active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a :href="route('admin.email.templates.edit', tpl.id)"
                                class="inline-flex items-center gap-1 bg-zinc-800 border border-zinc-700 text-zinc-300 text-xs font-semibold rounded-lg px-2.5 py-1.5 hover:bg-zinc-700 transition-colors">
                                <Pencil :size="11" />
                                Edit
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
