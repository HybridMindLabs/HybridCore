<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import PageEditorForm, { type PageFormData } from '@/components/Admin/PageBuilder/PageEditorForm.vue';
import type { BlockType, GameOption } from '@/components/Admin/PageBuilder/types';

defineProps<{ blockTypes: BlockType[]; games: GameOption[] }>();

const form = useForm<PageFormData>({
    title: '',
    slug: '',
    body: '',
    blocks: [],
    format: 'blocks',
    layout: 'default',
    status: 'draft',
    published_at: '',
    seo_title: '',
    seo_description: '',
    seo_og_image: '',
});

function submit() {
    form.post(route('admin.pages.store'));
}

// Ctrl/Cmd+S saves, like any editor.
useKeyboardShortcuts({
    'ctrl+s': () => !form.processing && submit(),
    'meta+s': () => !form.processing && submit(),
});
</script>

<template>
    <Head title="New Page" />
    <AdminLayout title="New Page">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-sm">
                <Link :href="route('admin.pages.index')" class="flex items-center gap-1.5 text-zinc-500 hover:text-zinc-100 transition-colors">
                    <ChevronLeft :size="13" :stroke-width="1.75" /> Pages
                </Link>
                <span class="text-zinc-700">/</span>
                <span class="text-zinc-300">New page</span>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('admin.pages.index')" class="px-4 py-2 text-sm text-zinc-500 hover:text-zinc-100 transition-colors">Cancel</Link>
                <button type="button" :disabled="form.processing"
                    class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                    title="Ctrl+S" @click="submit">
                    {{ form.processing ? 'Creating…' : 'Create Page' }}
                </button>
            </div>
        </div>

        <PageEditorForm :form="form" :block-types="blockTypes" :games="games" />
    </AdminLayout>
</template>
