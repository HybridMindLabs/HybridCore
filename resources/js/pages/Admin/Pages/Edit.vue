<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ExternalLink } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import PageEditorForm, { type PageFormData, type RevisionRow } from '@/components/Admin/PageBuilder/PageEditorForm.vue';
import type { BlockType, GameOption, PageBlock } from '@/components/Admin/PageBuilder/types';

interface PageDetail {
    id: number;
    title: string;
    slug: string;
    body: string | null;
    blocks: PageBlock[];
    format: PageFormData['format'];
    layout: string;
    status: string;
    published_at: string | null;
    is_scheduled: boolean;
    is_live: boolean;
    seo_title: string | null;
    seo_description: string | null;
    seo_og_image: string | null;
}

const props = defineProps<{ page: PageDetail; blockTypes: BlockType[]; games: GameOption[]; revisions: RevisionRow[] }>();

const form = useForm<PageFormData>({
    title: props.page.title,
    slug: props.page.slug,
    body: props.page.body ?? '',
    blocks: props.page.blocks ?? [],
    format: props.page.format ?? 'markdown',
    layout: props.page.layout ?? 'default',
    status: props.page.status,
    published_at: props.page.published_at ?? '',
    seo_title: props.page.seo_title ?? '',
    seo_description: props.page.seo_description ?? '',
    seo_og_image: props.page.seo_og_image ?? '',
});

function submit() {
    form.put(route('admin.pages.update', props.page.id), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

// Ctrl/Cmd+S saves, like any editor.
useKeyboardShortcuts({
    'ctrl+s': () => !form.processing && submit(),
    'meta+s': () => !form.processing && submit(),
});
</script>

<template>
    <Head :title="`Edit — ${page.title}`" />
    <AdminLayout title="Edit Page">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-sm min-w-0">
                <Link :href="route('admin.pages.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                    <ChevronLeft :size="13" :stroke-width="1.75" /> Pages
                </Link>
                <span class="text-zinc-700">/</span>
                <span class="text-zinc-300 truncate max-w-[200px]">{{ page.title }}</span>
                <span v-if="page.is_scheduled" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-400">Scheduled</span>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="form.isDirty" class="flex items-center gap-1.5 text-amber-400 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400" />
                    Unsaved changes
                </span>
                <a v-if="page.is_live" :href="`/${page.slug}`" target="_blank"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-zinc-500 hover:text-zinc-100 border border-zinc-800/70 hover:border-zinc-700 transition-colors">
                    <ExternalLink :size="11" :stroke-width="1.75" /> View
                </a>
                <Link :href="route('admin.pages.index')" class="px-4 py-2 text-sm text-zinc-500 hover:text-zinc-100 transition-colors">Back</Link>
                <button type="button" :disabled="form.processing"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                    title="Ctrl+S" @click="submit">
                    {{ form.processing ? 'Saving…' : 'Save Changes' }}
                </button>
            </div>
        </div>

        <PageEditorForm :form="form" :block-types="blockTypes" :games="games" :page-id="page.id" :revisions="revisions" />
    </AdminLayout>
</template>
