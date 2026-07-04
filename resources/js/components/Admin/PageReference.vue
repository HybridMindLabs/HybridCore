<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown, ChevronRight, Copy, Check } from '@lucide/vue';

const props = defineProps<{ format: 'markdown' | 'html' }>();

const sections = ref({
    placeholders: true,
    markdown: false,
    html: false,
    layouts: false,
});

const copied = ref<string | null>(null);

function copy(text: string) {
    navigator.clipboard.writeText(text);
    copied.value = text;
    setTimeout(() => { copied.value = null; }, 1500);
}

const PLACEHOLDERS = [
    { tag: '{site_name}', desc: 'The name of the site (from Settings)' },
    { tag: '{contact_email}', desc: 'Contact email address' },
];

const MD_CHEATSHEET = [
    { syntax: '# Heading 1', result: 'Large title' },
    { syntax: '## Heading 2', result: 'Section heading' },
    { syntax: '**bold**', result: 'Bold text' },
    { syntax: '*italic*', result: 'Italic text' },
    { syntax: '[text](url)', result: 'Hyperlink' },
    { syntax: '![alt](url)', result: 'Image' },
    { syntax: '- item', result: 'Unordered list' },
    { syntax: '1. item', result: 'Ordered list' },
    { syntax: '> quote', result: 'Blockquote' },
    { syntax: '`code`', result: 'Inline code' },
    { syntax: '```\\ncode\\n```', result: 'Code block' },
    { syntax: '---', result: 'Horizontal rule' },
    { syntax: '| A | B |\\n|---|---|\\n| 1 | 2 |', result: 'Table' },
];

const HTML_SNIPPETS = [
    { label: 'Section', code: '<section class="mb-6">\n  <h2>Section Title</h2>\n  <p>Content here...</p>\n</section>' },
    { label: 'Info box', code: '<div class="p-4 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-300">\n  <strong>Note:</strong> Your message here.\n</div>' },
    { label: 'Warning box', code: '<div class="p-4 rounded-lg bg-yellow-500/10 border border-yellow-500/20 text-yellow-300">\n  <strong>Warning:</strong> Your message here.\n</div>' },
    { label: 'Two columns', code: '<div class="grid grid-cols-2 gap-6">\n  <div>Column 1</div>\n  <div>Column 2</div>\n</div>' },
    { label: 'Button link', code: '<a href="/url" class="inline-flex items-center px-4 py-2 rounded-lg bg-cyan-500 text-white font-medium hover:bg-cyan-400 transition-colors">Click here</a>' },
    { label: 'Ordered list', code: '<ol class="list-decimal pl-5 space-y-1">\n  <li>First item</li>\n  <li>Second item</li>\n</ol>' },
    { label: 'Definition list', code: '<dl class="space-y-2">\n  <dt class="font-semibold">Term</dt>\n  <dd class="pl-4 text-slate-400">Definition</dd>\n</dl>' },
];

const LAYOUTS = [
    { key: 'default', label: 'Default', desc: 'max-w-3xl — standard article width', icon: '▬' },
    { key: 'wide', label: 'Wide', desc: 'max-w-5xl — wider content area', icon: '━' },
    { key: 'centered', label: 'Centered', desc: 'max-w-2xl — narrow, centered text', icon: '▪' },
    { key: 'sidebar', label: 'Sidebar', desc: '2/3 + 1/3 grid (coming soon)', icon: '▬▪' },
];
</script>

<template>
    <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden text-xs">
        <div class="px-4 py-3 border-b border-zinc-800/70">
            <span class="font-semibold text-zinc-100">Reference</span>
            <span class="text-zinc-600 ml-2">— available tools & syntax</span>
        </div>

        <!-- Placeholders -->
        <div class="border-b border-[#1a2236]">
            <button
                type="button"
                class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-[#0d0d0f] transition-colors"
                @click="sections.placeholders = !sections.placeholders"
            >
                <span class="font-medium text-zinc-400">Placeholders</span>
                <component :is="sections.placeholders ? ChevronDown : ChevronRight" :size="12" class="text-zinc-600" />
            </button>
            <div v-if="sections.placeholders" class="px-4 pb-3 flex flex-col gap-1.5">
                <p class="text-zinc-600 mb-2">These are replaced automatically when the page is rendered:</p>
                <div
                    v-for="p in PLACEHOLDERS"
                    :key="p.tag"
                    class="flex items-start justify-between gap-2 group"
                >
                    <div>
                        <code class="text-blue-400 bg-[#09090b] px-1.5 py-0.5 rounded font-mono">{{ p.tag }}</code>
                        <span class="text-zinc-600 ml-2">{{ p.desc }}</span>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity text-zinc-600 hover:text-blue-400"
                        @click="copy(p.tag)"
                    >
                        <component :is="copied === p.tag ? Check : Copy" :size="11" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Markdown cheatsheet -->
        <div v-if="format === 'markdown'" class="border-b border-[#1a2236]">
            <button
                type="button"
                class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-[#0d0d0f] transition-colors"
                @click="sections.markdown = !sections.markdown"
            >
                <span class="font-medium text-zinc-400">Markdown Syntax</span>
                <component :is="sections.markdown ? ChevronDown : ChevronRight" :size="12" class="text-zinc-600" />
            </button>
            <div v-if="sections.markdown" class="px-4 pb-3 flex flex-col gap-1">
                <div
                    v-for="item in MD_CHEATSHEET"
                    :key="item.syntax"
                    class="flex items-center justify-between gap-3 py-1 border-b border-[#0d1525] last:border-0 group"
                >
                    <code class="text-blue-400 font-mono shrink-0">{{ item.syntax.replace('\\n', ' ') }}</code>
                    <span class="text-zinc-600 text-right">{{ item.result }}</span>
                    <button
                        type="button"
                        class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity text-zinc-600 hover:text-blue-400"
                        @click="copy(item.syntax.replace(/\\\\n/g, '\n'))"
                    >
                        <component :is="copied === item.syntax ? Check : Copy" :size="11" />
                    </button>
                </div>
            </div>
        </div>

        <!-- HTML snippets -->
        <div v-if="format === 'html'" class="border-b border-[#1a2236]">
            <button
                type="button"
                class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-[#0d0d0f] transition-colors"
                @click="sections.html = !sections.html"
            >
                <span class="font-medium text-zinc-400">HTML Snippets</span>
                <component :is="sections.html ? ChevronDown : ChevronRight" :size="12" class="text-zinc-600" />
            </button>
            <div v-if="sections.html" class="px-4 pb-3 flex flex-col gap-2">
                <p class="text-zinc-600 mb-1">Click to copy a ready-to-use snippet:</p>
                <div
                    v-for="snippet in HTML_SNIPPETS"
                    :key="snippet.label"
                    class="flex items-center justify-between gap-2 p-2 rounded bg-[#09090b] border border-zinc-800/70 group cursor-pointer hover:border-blue-500/30 transition-colors"
                    @click="copy(snippet.code)"
                >
                    <span class="text-zinc-400">{{ snippet.label }}</span>
                    <component :is="copied === snippet.code ? Check : Copy" :size="11" class="text-zinc-600 group-hover:text-blue-400 transition-colors" />
                </div>
            </div>
        </div>

        <!-- Layouts -->
        <div>
            <button
                type="button"
                class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-[#0d0d0f] transition-colors"
                @click="sections.layouts = !sections.layouts"
            >
                <span class="font-medium text-zinc-400">Page Layouts</span>
                <component :is="sections.layouts ? ChevronDown : ChevronRight" :size="12" class="text-zinc-600" />
            </button>
            <div v-if="sections.layouts" class="px-4 pb-3 flex flex-col gap-1.5">
                <div v-for="l in LAYOUTS" :key="l.key" class="flex items-start gap-2">
                    <span class="font-mono text-zinc-700 shrink-0 w-6 text-center">{{ l.icon }}</span>
                    <div>
                        <span class="text-zinc-400 font-medium">{{ l.label }}</span>
                        <span class="text-zinc-600 ml-2">{{ l.desc }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
