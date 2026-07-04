<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Scale, Plus, Pencil, Trash2, ExternalLink, Lock } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface LegalPage {
    id: number;
    slug: string;
    title: string;
    subtitle: string | null;
    is_system: boolean;
    sort_order: number;
    content_updated_at: string | null;
    updated_at: string;
}

const props = defineProps<{ pages: LegalPage[] }>();

function deletePage(slug: string) {
    if (!confirm('Delete this page? This cannot be undone.')) return;
    router.delete(route('admin.legal.destroy', slug));
}
</script>

<template>
    <Head title="Legal Pages" />
    <AdminLayout title="Legal Pages">

        <PageHeader
            title="Legal Pages"
            description="Manage public legal pages like Terms, Privacy, and Cookies."
            :icon="Scale"
        >
            <Link
                :href="route('admin.legal.create')"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-[#0a0f1a] hover:bg-blue-600 transition-colors"
            >
                <Plus :size="14" :stroke-width="2" />
                New page
            </Link>
        </PageHeader>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div v-if="pages.length === 0" class="p-10 text-center">
                <Scale :size="24" :stroke-width="1.5" class="mx-auto mb-3 text-zinc-700" />
                <p class="text-zinc-500 text-sm">No legal pages yet.</p>
                <Link :href="route('admin.legal.create')" class="mt-3 inline-block text-xs text-blue-400 hover:underline">Create your first page</Link>
            </div>

            <div v-else class="divide-y divide-zinc-800/50">
                <div
                    v-for="page in pages"
                    :key="page.id"
                    class="flex items-center gap-4 px-5 py-4 hover:bg-white/[0.02] transition-colors group"
                >
                    <!-- Icon -->
                    <div class="w-9 h-9 rounded-lg bg-zinc-800/60 border border-zinc-700/50 flex items-center justify-center shrink-0">
                        <Scale :size="15" :stroke-width="1.75" class="text-zinc-400" />
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-zinc-100">{{ page.title }}</span>
                            <span
                                v-if="page.is_system"
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 flex items-center gap-1"
                            >
                                <Lock :size="9" />
                                system
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="text-xs text-zinc-600 font-mono">/legal/{{ page.slug }}</span>
                            <span v-if="page.content_updated_at" class="text-xs text-zinc-600">
                                Updated {{ page.content_updated_at }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a
                            :href="`/legal/${page.slug}`"
                            target="_blank"
                            class="p-2 rounded-lg text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors"
                            title="Preview"
                        >
                            <ExternalLink :size="14" :stroke-width="1.75" />
                        </a>
                        <Link
                            :href="route('admin.legal.edit', page.slug)"
                            class="p-2 rounded-lg text-zinc-600 hover:text-zinc-300 hover:bg-zinc-800 transition-colors"
                            title="Edit"
                        >
                            <Pencil :size="14" :stroke-width="1.75" />
                        </Link>
                        <button
                            v-if="!page.is_system"
                            type="button"
                            class="p-2 rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-400/10 transition-colors"
                            title="Delete"
                            @click="deletePage(page.slug)"
                        >
                            <Trash2 :size="14" :stroke-width="1.75" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
