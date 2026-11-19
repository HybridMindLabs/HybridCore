<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, type InertiaForm } from '@inertiajs/vue3';
import { FileText, Blocks, FileCode2, Eye, History, RotateCcw, CalendarClock } from '@lucide/vue';
import MarkdownEditor from '@/components/Admin/MarkdownEditor.vue';
import PageReference from '@/components/Admin/PageReference.vue';
import PageBuilder from './PageBuilder.vue';
import PagePreview from './PagePreview.vue';
import { newBlockId, type BlockType, type GameOption, type PageBlock } from './types';

export interface PageFormData {
    title: string;
    slug: string;
    body: string;
    blocks: PageBlock[];
    format: 'markdown' | 'html' | 'blocks';
    layout: string;
    status: string;
    published_at: string;
    seo_title: string;
    seo_description: string;
    seo_og_image: string;
}

export interface RevisionRow {
    id: number;
    title: string;
    format: string;
    blocks: number;
    user: string | null;
    created_at: string | null;
}

const props = defineProps<{
    form: InertiaForm<PageFormData>;
    blockTypes: BlockType[];
    games: GameOption[];
    pageId?: number;
    revisions?: RevisionRow[];
}>();

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder:text-zinc-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20';

const LAYOUTS = [
    { key: 'default', label: 'Default', desc: 'max-w-3xl' },
    { key: 'wide', label: 'Wide', desc: 'max-w-5xl' },
    { key: 'centered', label: 'Centered', desc: 'max-w-2xl' },
];

const isBlocks = computed(() => props.form.format === 'blocks');
const textFormat = computed(() => (props.form.format === 'html' ? 'html' : 'markdown'));
const showPreview = ref(false);

/**
 * Switching a Markdown page to blocks carries its text over as a first Text
 * block; the original body stays saved, so switching back loses nothing.
 */
function useBlocks() {
    if (!props.form.blocks.length && props.form.body.trim() && props.form.format === 'markdown') {
        props.form.blocks.push({ id: newBlockId(), type: 'text', data: { body: props.form.body } });
    }
    props.form.format = 'blocks';
}

const isFuture = computed(() => !!props.form.published_at && new Date(props.form.published_at) > new Date());

const confirmRestore = ref<number | null>(null);
function restore(id: number) {
    if (confirmRestore.value !== id) {
        confirmRestore.value = id;
        return;
    }
    confirmRestore.value = null;
    router.post(route('admin.pages.revisions.restore', { page: props.pageId, revision: id }), {}, {
        preserveScroll: true,
        // Re-open the page so the editor starts from the restored content.
        onSuccess: () => router.visit(window.location.href, { preserveState: false }),
    });
}

// Leaving with unsaved edits asks first — both closing the tab and clicking
// away inside the admin. Saving itself and partial reloads are never blocked.
function onBeforeUnload(e: BeforeUnloadEvent) {
    if (props.form.isDirty && !props.form.processing) e.preventDefault();
}
let stopGuard: (() => void) | null = null;
onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    stopGuard = router.on('before', (event) => {
        const visit = event.detail.visit;
        if (visit.method !== 'get' || visit.only.length || !props.form.isDirty) return;
        if (visit.url.pathname === window.location.pathname) return;
        if (!window.confirm('You have unsaved changes on this page. Leave without saving?')) event.preventDefault();
    });
});
onUnmounted(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    stopGuard?.();
});

const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '');
</script>

<template>
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-5">

        <!-- Main (3/4) -->
        <div class="xl:col-span-3 flex flex-col gap-4">
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <label class="flex items-center gap-1.5 text-xs font-medium text-zinc-400 mb-2">
                    <FileText :size="12" :stroke-width="1.75" class="text-blue-400" />
                    Title <span class="text-red-400">*</span>
                </label>
                <input v-model="form.title" type="text" :class="inputClass" placeholder="Page title…" />
                <p v-if="form.errors.title" class="mt-1.5 text-xs text-red-400">{{ form.errors.title }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg border border-zinc-800/70 bg-admin-surface p-1" role="tablist" aria-label="Editor">
                    <button type="button" role="tab" :aria-selected="isBlocks"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors"
                        :class="isBlocks ? 'bg-blue-500 text-white' : 'text-zinc-400 hover:text-zinc-100'" @click="useBlocks">
                        <Blocks :size="13" /> Blocks
                    </button>
                    <button type="button" role="tab" :aria-selected="!isBlocks"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors"
                        :class="!isBlocks ? 'bg-blue-500 text-white' : 'text-zinc-400 hover:text-zinc-100'"
                        @click="form.format = textFormat">
                        <FileCode2 :size="13" /> Markdown / HTML
                    </button>
                </div>
                <button v-if="isBlocks" type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-zinc-800/70 text-zinc-300 hover:text-white hover:border-zinc-600 transition-colors"
                    @click="showPreview = true">
                    <Eye :size="13" /> Preview
                </button>
            </div>

            <PageBuilder v-if="isBlocks" v-model="form.blocks" :block-types="blockTypes" :games="games" />
            <div v-else class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
                <MarkdownEditor
                    v-model="form.body"
                    :format="textFormat"
                    :min-height="600"
                    @update:format="form.format = $event as 'markdown' | 'html'"
                />
            </div>
            <p v-if="form.errors.body || form.errors.blocks" class="text-xs text-red-400">{{ form.errors.body || form.errors.blocks }}</p>
        </div>

        <!-- Sidebar -->
        <div class="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="page-status">Status</label>
                    <select id="page-status" v-model="form.status" :class="inputClass">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="flex items-center gap-1.5 text-zinc-500 text-xs font-medium" for="page-publish-at">
                        <CalendarClock :size="12" /> Publish at <span class="text-zinc-600">(optional)</span>
                    </label>
                    <input id="page-publish-at" v-model="form.published_at" type="datetime-local" :class="inputClass + ' [color-scheme:dark]'" />
                    <p v-if="form.status === 'published' && isFuture" class="text-[11px] text-amber-400">
                        Scheduled — goes live {{ when(form.published_at) }}.
                    </p>
                    <p v-else class="text-[11px] text-zinc-600">Leave empty to publish as soon as the status is Published.</p>
                    <p v-if="form.errors.published_at" class="text-xs text-red-400">{{ form.errors.published_at }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="page-slug">Slug</label>
                    <input id="page-slug" v-model="form.slug" type="text" :class="inputClass + ' font-mono text-xs'" placeholder="auto from title" />
                    <p v-if="form.errors.slug" class="text-xs text-red-400">{{ form.errors.slug }}</p>
                </div>
            </div>

            <div v-if="!isBlocks" class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <p class="text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-3">Layout</p>
                <div class="flex flex-col gap-2">
                    <label v-for="l in LAYOUTS" :key="l.key"
                        class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                        :class="form.layout === l.key ? 'border-blue-500/50 bg-blue-500/5' : 'border-zinc-800/70 hover:border-zinc-700'">
                        <input v-model="form.layout" type="radio" :value="l.key" class="sr-only" />
                        <div class="w-12 h-8 rounded border border-zinc-800/70 bg-zinc-900 flex items-center justify-center shrink-0">
                            <div class="h-4 rounded-sm bg-blue-500/30" :class="l.key === 'default' ? 'w-8' : l.key === 'wide' ? 'w-10' : 'w-6'" />
                        </div>
                        <div>
                            <p class="text-zinc-100 text-xs font-medium">{{ l.label }}</p>
                            <p class="text-zinc-600 text-[10px] font-mono">{{ l.desc }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                <p class="text-zinc-500 text-xs font-semibold uppercase tracking-wider">SEO</p>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="page-seo-title">SEO Title</label>
                    <input id="page-seo-title" v-model="form.seo_title" type="text" :class="inputClass" placeholder="Defaults to page title" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="page-seo-desc">Meta Description</label>
                    <textarea id="page-seo-desc" v-model="form.seo_description" rows="3" :class="inputClass + ' resize-none text-xs'" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-zinc-500 text-xs font-medium" for="page-og">OG Image URL</label>
                    <input id="page-og" v-model="form.seo_og_image" type="text" :class="inputClass + ' text-xs font-mono'" placeholder="https://…" />
                </div>
            </div>

            <div v-if="revisions" class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
                <p class="flex items-center gap-1.5 text-zinc-500 text-xs font-semibold uppercase tracking-wider mb-3">
                    <History :size="12" /> History
                </p>
                <p v-if="!revisions.length" class="text-xs text-zinc-600">Earlier versions appear here after you save changes.</p>
                <ul v-else class="flex flex-col gap-1 max-h-72 overflow-y-auto -mx-1 px-1">
                    <li v-for="r in revisions" :key="r.id" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-white/[0.03]">
                        <div class="min-w-0">
                            <p class="text-xs text-zinc-200 truncate">{{ when(r.created_at) }}</p>
                            <p class="text-[11px] text-zinc-600 truncate">
                                {{ r.user ?? 'Unknown' }} · {{ r.format === 'blocks' ? `${r.blocks} blocks` : r.format }}
                            </p>
                        </div>
                        <button type="button"
                            class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium transition-colors"
                            :class="confirmRestore === r.id ? 'bg-amber-500 text-black' : 'text-zinc-400 hover:text-white hover:bg-white/[0.06]'"
                            @click="restore(r.id)">
                            <RotateCcw :size="11" /> {{ confirmRestore === r.id ? 'Confirm' : 'Restore' }}
                        </button>
                    </li>
                </ul>
            </div>

            <PageReference v-if="!isBlocks" :format="textFormat" />
        </div>

        <PagePreview v-if="showPreview" :blocks="form.blocks" :title="form.title" @close="showPreview = false" />
    </div>
</template>
