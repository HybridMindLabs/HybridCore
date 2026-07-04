<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Plus, Pencil, Trash2, ExternalLink, Globe, FileX } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import EmptyState from '@/components/UI/EmptyState.vue';

interface PageRow {
    id: number;
    title: string;
    slug: string;
    status: string;
    updated_at: string | null;
}

defineProps<{ pages: PageRow[] }>();

function destroy(page: PageRow) {
    if (confirm(`Delete page "${page.title}"?`)) {
        router.delete(route('admin.pages.destroy', page.id));
    }
}
</script>

<template>
    <Head title="Pages" />
    <AdminLayout title="Pages">

        <PageHeader title="Pages" description="Manage public website pages and their content." :icon="FileText">
            <template #actions>
                <Link
                    :href="route('admin.pages.create')"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                >
                    <Plus :size="14" :stroke-width="2" /> New Page
                </Link>
            </template>
        </PageHeader>

        <EmptyState
            v-if="pages.length === 0"
            :icon="FileText"
            title="No pages yet"
            description="Create your first page to publish content on the public site."
        >
            <template #action>
                <Link
                    :href="route('admin.pages.create')"
                    class="flex items-center gap-1.5 bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-400 transition-colors"
                >
                    <Plus :size="14" :stroke-width="2" /> New Page
                </Link>
            </template>
        </EmptyState>

        <div v-else class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800/70">
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide">Title</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide hidden sm:table-cell">Slug</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-3 text-zinc-600 text-xs font-semibold uppercase tracking-wide hidden md:table-cell">Updated</th>
                        <th class="px-4 py-3 w-28" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="page in pages"
                        :key="page.id"
                        class="border-b border-zinc-800/50 last:border-0 hover:bg-zinc-900/30 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="route('admin.pages.edit', page.id)"
                                class="text-zinc-100 font-medium hover:text-blue-400 transition-colors"
                            >
                                {{ page.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 text-xs font-mono hidden sm:table-cell">
                            /{{ page.slug }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full"
                                :class="page.status === 'published'
                                    ? 'bg-emerald-500/10 text-emerald-400'
                                    : 'bg-zinc-800 text-zinc-500'"
                            >
                                <Globe v-if="page.status === 'published'" :size="10" :stroke-width="2" />
                                <FileX v-else :size="10" :stroke-width="2" />
                                {{ page.status === 'published' ? 'Published' : 'Draft' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 text-xs hidden md:table-cell">
                            {{ page.updated_at }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 justify-end">
                                <a
                                    v-if="page.status === 'published'"
                                    :href="'/' + page.slug"
                                    target="_blank"
                                    title="View page"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-blue-400 hover:bg-blue-500/10 transition-colors"
                                >
                                    <ExternalLink :size="13" :stroke-width="1.75" />
                                </a>
                                <Link
                                    :href="route('admin.pages.edit', page.id)"
                                    title="Edit"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800 transition-colors"
                                >
                                    <Pencil :size="13" :stroke-width="1.75" />
                                </Link>
                                <button
                                    type="button"
                                    title="Delete"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                                    @click="destroy(page)"
                                >
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
