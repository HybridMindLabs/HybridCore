<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch, shallowRef } from 'vue';
import { EditorState } from '@codemirror/state';
import { EditorView, keymap, placeholder as placeholderExt } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { markdown } from '@codemirror/lang-markdown';
import { syntaxHighlighting, defaultHighlightStyle } from '@codemirror/language';
import { marked } from 'marked';
import { Image, Bold, Italic, Link2, Code, List, Heading2, Eye, EyeOff, Upload } from '@lucide/vue';

const props = defineProps<{
    modelValue: string;
    uploadRoute?: string;
}>();
const emit = defineEmits<{
    'update:modelValue': [v: string];
}>();

const editorEl = ref<HTMLElement | null>(null);
const previewMode = ref(false);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
let view: EditorView | null = null;

const previewHtml = ref('');

async function refreshPreview() {
    previewHtml.value = await marked.parse(props.modelValue, { gfm: true, breaks: true }) as string;
}

watch(() => props.modelValue, (v) => {
    if (previewMode.value) refreshPreview();
    if (view && view.state.doc.toString() !== v) {
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: v } });
    }
});

watch(previewMode, (v) => { if (v) refreshPreview(); });

onMounted(() => {
    const state = EditorState.create({
        doc: props.modelValue,
        extensions: [
            history(),
            markdown(),
            syntaxHighlighting(defaultHighlightStyle),
            keymap.of([...defaultKeymap, ...historyKeymap]),
            placeholderExt('Write your article in Markdown...'),
            EditorView.theme({
                '&': { background: 'transparent', height: '100%' },
                '.cm-scroller': { fontFamily: "'JetBrains Mono', 'Fira Code', monospace", fontSize: '13px', lineHeight: '1.7', overflow: 'auto' },
                '.cm-content': { padding: '16px', color: '#e4e4e7', caretColor: '#3b82f6' },
                '.cm-cursor': { borderLeftColor: '#3b82f6' },
                '.cm-activeLine': { background: 'rgba(255,255,255,0.03)' },
                '.cm-line': { padding: '0 2px' },
                '.tok-heading': { color: '#93c5fd', fontWeight: '700' },
                '.tok-strong': { color: '#f4f4f5', fontWeight: '700' },
                '.tok-emphasis': { color: '#d4d4d8', fontStyle: 'italic' },
                '.tok-link': { color: '#60a5fa' },
                '.tok-url': { color: '#34d399' },
                '.tok-monospace': { color: '#86efac', fontFamily: 'monospace' },
                '.tok-comment': { color: '#71717a' },
                '.cm-placeholder': { color: '#52525b' },
            }),
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    emit('update:modelValue', update.state.doc.toString());
                }
            }),
        ],
    });

    view = new EditorView({ state, parent: editorEl.value! });
});

onUnmounted(() => view?.destroy());

// ── Toolbar helpers ───────────────────────────────────────────────────────────
function wrap(before: string, after: string, placeholder = '') {
    if (!view) return;
    const { from, to } = view.state.selection.main;
    const sel = view.state.doc.sliceString(from, to) || placeholder;
    view.dispatch({
        changes: { from, to, insert: before + sel + after },
        selection: { anchor: from + before.length, head: from + before.length + sel.length },
    });
    view.focus();
}

function insertLine(prefix: string) {
    if (!view) return;
    const { from } = view.state.selection.main;
    const line = view.state.doc.lineAt(from);
    view.dispatch({
        changes: { from: line.from, to: line.from, insert: prefix },
        selection: { anchor: line.from + prefix.length },
    });
    view.focus();
}

const tools = [
    { icon: Heading2, title: 'Heading', action: () => insertLine('## ') },
    { icon: Bold,     title: 'Bold',    action: () => wrap('**', '**', 'bold text') },
    { icon: Italic,   title: 'Italic',  action: () => wrap('_', '_', 'italic') },
    { icon: Link2,    title: 'Link',    action: () => wrap('[', '](https://)', 'link text') },
    { icon: Code,     title: 'Code',    action: () => wrap('`', '`', 'code') },
    { icon: List,     title: 'List',    action: () => insertLine('- ') },
    { icon: Image,    title: 'Image',   action: () => wrap('![', '](https://)', 'alt text') },
];

// ── Image upload ──────────────────────────────────────────────────────────────
async function handleUpload(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file || !props.uploadRoute) return;
    uploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        // Get CSRF token from meta tag
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        const res = await fetch(props.uploadRoute, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const json = await res.json();
        if (json.url && view) {
            const { from } = view.state.selection.main;
            const md = `![image](${json.url})`;
            view.dispatch({ changes: { from, to: from, insert: md }, selection: { anchor: from + md.length } });
            view.focus();
        }
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}
</script>

<template>
    <div class="rounded-2xl border border-zinc-800/70 bg-[#111113] overflow-hidden flex flex-col" style="min-height: 520px;">
        <!-- Toolbar -->
        <div class="flex items-center gap-0.5 px-3 py-2 border-b border-zinc-800/60 bg-[#1a1a1e] flex-wrap">
            <button v-for="t in tools" :key="t.title" type="button" :title="t.title" @click="t.action"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-zinc-200 hover:bg-white/5 transition">
                <component :is="t.icon" :size="13" :stroke-width="2" />
            </button>

            <div class="w-px h-4 bg-zinc-800/80 mx-1" />

            <!-- Image upload -->
            <button v-if="uploadRoute" type="button" title="Upload Image"
                :disabled="uploading"
                @click="fileInput?.click()"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-blue-400 hover:bg-blue-500/8 transition disabled:opacity-40">
                <Upload :size="13" :stroke-width="2" />
            </button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleUpload" />

            <div class="flex-1" />

            <!-- Preview toggle -->
            <button type="button" :title="previewMode ? 'Editor' : 'Preview'" @click="previewMode = !previewMode"
                class="flex items-center gap-1.5 px-2.5 h-7 rounded-lg border text-[11px] font-bold transition"
                :class="previewMode
                    ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                    : 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'">
                <component :is="previewMode ? EyeOff : Eye" :size="11" :stroke-width="2.2" />
                {{ previewMode ? 'Edit' : 'Preview' }}
            </button>
        </div>

        <!-- Editor / Preview pane -->
        <div class="flex-1 overflow-auto relative">
            <!-- CodeMirror editor -->
            <div v-show="!previewMode" ref="editorEl" class="h-full" />

            <!-- Markdown preview -->
            <div v-if="previewMode"
                class="prose prose-invert prose-zinc max-w-none p-5
                    prose-headings:font-black prose-h1:text-[22px] prose-h2:text-[18px]
                    prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline
                    prose-code:text-blue-300 prose-code:bg-zinc-900/60 prose-code:px-1 prose-code:py-0.5 prose-code:rounded prose-code:text-[12px]
                    prose-pre:bg-zinc-900/80 prose-pre:border prose-pre:border-zinc-800/70 prose-pre:rounded-xl prose-pre:text-[12px]
                    prose-blockquote:border-l-blue-500/40 prose-blockquote:text-zinc-400 prose-blockquote:bg-zinc-900/30 prose-blockquote:py-0.5 prose-blockquote:rounded-r-lg
                    prose-img:rounded-xl prose-img:border prose-img:border-zinc-800/70
                    prose-hr:border-zinc-800/70"
                v-html="previewHtml" />
        </div>

        <!-- Footer: word count -->
        <div class="px-4 py-1.5 border-t border-zinc-800/50 flex items-center gap-4 text-[10px] text-zinc-700">
            <span>{{ modelValue.split(/\s+/).filter(Boolean).length }} words</span>
            <span>{{ modelValue.length }} chars</span>
            <span>~{{ Math.max(1, Math.round(modelValue.split(/\s+/).filter(Boolean).length / 200)) }} min read</span>
        </div>
    </div>
</template>