<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { BookOpen, Lock, Clock, ArrowLeft, ArrowRight } from '@lucide/vue';
import { marked } from 'marked';

interface Rule {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    content: string | null;
    is_system: boolean;
    updated_at: string;
}

interface RuleNav {
    id: number;
    slug: string;
    title: string;
    is_system: boolean;
    excerpt: string | null;
}

interface Seo {
    title: string;
    description: string;
}

const props = defineProps<{
    rule: Rule;
    allRules: RuleNav[];
    seo: Seo;
}>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const parsedContent = ref('');

interface TocEntry { id: string; text: string; level: number }
const toc = ref<TocEntry[]>([]);
const activeId = ref('');

onMounted(async () => {
    marked.setOptions({ breaks: true });
    const renderer = new marked.Renderer();
    const entries: TocEntry[] = [];

    renderer.heading = ({ text, depth }: { text: string; depth: number }) => {
        const id = text.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        if (depth <= 3) entries.push({ id, text, level: depth });
        return `<h${depth} id="${id}">${text}</h${depth}>`;
    };

    parsedContent.value = await marked.parse(props.rule.content ?? '', { renderer }) as string;
    toc.value = entries;

    await nextTick();

    const observer = new IntersectionObserver(
        (obs) => { for (const e of obs) { if (e.isIntersecting) activeId.value = e.target.id; } },
        { rootMargin: '-20% 0px -70% 0px' },
    );
    document.querySelectorAll('.rule-body h1,.rule-body h2,.rule-body h3').forEach(el => observer.observe(el));
});

const formattedDate = computed(() => {
    try {
        return new Date(props.rule.updated_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch { return props.rule.updated_at; }
});

function scrollTo(id: string) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const ruleIndex = computed(() => props.allRules.findIndex(r => r.slug === props.rule.slug));
const prevRule = computed(() => ruleIndex.value > 0 ? props.allRules[ruleIndex.value - 1] : null);
const nextRule = computed(() => ruleIndex.value < props.allRules.length - 1 ? props.allRules[ruleIndex.value + 1] : null);
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
        >
            <!-- Glows + dot grid (same as Home) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-12 sm:py-16">
                <Breadcrumb :items="[{ label: 'Home', href: '/' }, { label: 'Rules', href: '/rules' }, { label: rule.title }]" />

                <div class="max-w-2xl">
                    <div class="flex items-center gap-2 flex-wrap mb-5">
                        <span
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="dark ? 'border-blue-500/25 bg-blue-500/8 text-blue-400' : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                        >
                            <BookOpen :size="11" :stroke-width="2.2" />
                            Rule {{ String(ruleIndex + 1).padStart(2, '0') }} / {{ String(allRules.length).padStart(2, '0') }}
                        </span>
                        <span
                            v-if="rule.is_system"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="dark ? 'border-zinc-700/70 bg-zinc-800/50 text-zinc-500' : 'border-zinc-200 bg-white text-zinc-400'"
                        >
                            <Lock :size="10" :stroke-width="2.2" /> System
                        </span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ rule.title }}
                    </h1>

                    <p
                        v-if="rule.excerpt"
                        class="mt-4 text-[15px] leading-relaxed max-w-lg"
                        :class="dark ? 'text-zinc-400' : 'text-zinc-500'"
                    >{{ rule.excerpt }}</p>

                    <p class="flex items-center gap-1.5 mt-4 text-[12px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                        <Clock :size="12" :stroke-width="1.8" />
                        Last updated {{ formattedDate }}
                    </p>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <!-- ── Body ──────────────────────────────────────────────── -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div class="flex gap-6 items-start">

                <!-- Left sidebar — rule list + toc -->
                <aside class="hidden lg:flex flex-col gap-4 w-60 xl:w-64 shrink-0 sticky top-24 self-start">

                    <!-- All rules nav -->
                    <div class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <p class="px-4 py-3 border-b text-[11px] font-black uppercase tracking-widest"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-500' : 'border-zinc-100 bg-zinc-50 text-zinc-400'"
                        >All rules</p>
                        <nav class="flex flex-col p-2 gap-0.5">
                            <Link
                                v-for="(r, i) in allRules"
                                :key="r.slug"
                                :href="`/rules/${r.slug}`"
                                class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] transition-colors"
                                :class="r.slug === rule.slug
                                    ? (dark ? 'bg-blue-500/12 text-blue-400 font-semibold' : 'bg-blue-50 text-blue-600 font-semibold')
                                    : (dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.04]' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-50')"
                            >
                                <span class="text-[10px] font-black tabular-nums w-5 shrink-0"
                                    :class="r.slug === rule.slug
                                        ? (dark ? 'text-blue-400' : 'text-blue-500')
                                        : (dark ? 'text-zinc-700' : 'text-zinc-300')"
                                >{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="leading-snug truncate">{{ r.title }}</span>
                            </Link>
                        </nav>
                    </div>

                    <!-- TOC for current rule -->
                    <div v-if="toc.length" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <p class="px-4 py-3 border-b text-[11px] font-black uppercase tracking-widest"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-500' : 'border-zinc-100 bg-zinc-50 text-zinc-400'"
                        >On this page</p>
                        <nav class="flex flex-col p-2 gap-0.5">
                            <button
                                v-for="entry in toc"
                                :key="entry.id"
                                class="text-left text-[13px] py-1.5 transition-colors truncate border-l-2 px-3"
                                :class="[
                                    entry.level === 3 ? 'pl-6 text-[12px]' : '',
                                    activeId === entry.id
                                        ? (dark ? 'border-blue-500 text-blue-400' : 'border-blue-500 text-blue-600')
                                        : (dark ? 'border-transparent text-zinc-600 hover:text-zinc-300' : 'border-transparent text-zinc-500 hover:text-zinc-800'),
                                ]"
                                @click="scrollTo(entry.id)"
                            >{{ entry.text }}</button>
                        </nav>
                    </div>
                </aside>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div
                            class="rule-body p-6 sm:p-8"
                            :class="dark ? 'legal-dark' : 'legal-light'"
                            v-html="parsedContent"
                        />
                    </div>

                    <!-- Prev / next rule -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        <Link
                            v-if="prevRule"
                            :href="`/rules/${prevRule.slug}`"
                            class="group flex items-center gap-3 rounded-2xl border px-4 py-3.5 transition-colors"
                            :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700' : 'border-zinc-200 bg-white hover:border-zinc-300 shadow-sm'"
                        >
                            <ArrowLeft :size="14" :stroke-width="2" class="shrink-0 transition-transform group-hover:-translate-x-0.5"
                                :class="dark ? 'text-zinc-600 group-hover:text-blue-400' : 'text-zinc-400 group-hover:text-blue-600'" />
                            <span class="min-w-0">
                                <span class="block text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Previous</span>
                                <span class="block text-[13px] font-bold truncate" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ prevRule.title }}</span>
                            </span>
                        </Link>
                        <span v-else class="hidden sm:block" />
                        <Link
                            v-if="nextRule"
                            :href="`/rules/${nextRule.slug}`"
                            class="group flex items-center justify-end gap-3 rounded-2xl border px-4 py-3.5 text-right transition-colors"
                            :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700' : 'border-zinc-200 bg-white hover:border-zinc-300 shadow-sm'"
                        >
                            <span class="min-w-0">
                                <span class="block text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Next</span>
                                <span class="block text-[13px] font-bold truncate" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ nextRule.title }}</span>
                            </span>
                            <ArrowRight :size="14" :stroke-width="2" class="shrink-0 transition-transform group-hover:translate-x-0.5"
                                :class="dark ? 'text-zinc-600 group-hover:text-blue-400' : 'text-zinc-400 group-hover:text-blue-600'" />
                        </Link>
                    </div>

                    <!-- Mobile rule switcher -->
                    <div class="mt-8 lg:hidden">
                        <p class="text-[11px] font-black uppercase tracking-widest mb-3" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Other rules</p>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="r in allRules"
                                :key="r.slug"
                                :href="`/rules/${r.slug}`"
                                class="px-3 py-1.5 rounded-full text-[13px] border transition-all"
                                :class="r.slug === rule.slug
                                    ? (dark ? 'bg-blue-500/12 text-blue-400 border-blue-500/25 font-semibold' : 'bg-blue-50 text-blue-600 border-blue-200 font-semibold')
                                    : (dark ? 'text-zinc-500 border-zinc-800 hover:border-zinc-600 hover:text-zinc-300' : 'text-zinc-500 border-zinc-200 hover:border-zinc-400')"
                            >{{ r.title }}</Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </PublicLayout>
</template>

<style>
/* Reuse legal-body styles — just rename selector alias */
.rule-body {
    font-size: 0.9375rem;
    line-height: 1.8;
}
.rule-body h1, .rule-body h2, .rule-body h3, .rule-body h4 {
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-top: 2.25em;
    margin-bottom: 0.6em;
    line-height: 1.3;
    scroll-margin-top: 6rem;
}
.rule-body h2 { font-size: 1.2rem; padding-bottom: 0.5em; border-bottom-width: 1px; }
.rule-body h3 { font-size: 1.05rem; }
.rule-body > h2:first-child, .rule-body > h3:first-child { margin-top: 0; }
.rule-body p { margin-bottom: 1.1em; }
.rule-body ul, .rule-body ol { padding-left: 1.5em; margin-bottom: 1.1em; }
.rule-body ul { list-style-type: disc; }
.rule-body ol { list-style-type: decimal; }
.rule-body li { margin-bottom: 0.35em; }
.rule-body strong { font-weight: 700; }
.rule-body code {
    font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
    font-size: 0.82em;
    padding: 0.15em 0.4em;
    border-radius: 4px;
}
.rule-body a { text-decoration: underline; text-underline-offset: 2px; transition: opacity 0.15s; }
.rule-body a:hover { opacity: 0.75; }
.rule-body blockquote {
    border-left-width: 3px;
    padding: 0.75em 1.25em;
    border-radius: 0 6px 6px 0;
    margin: 1.25em 0;
    font-style: italic;
}
.rule-body hr { margin: 2em 0; border-top-width: 1px; }
.rule-body table { width: 100%; border-collapse: collapse; margin: 1.5em 0; font-size: 0.875em; border-radius: 8px; overflow: hidden; }
.rule-body thead { border-bottom-width: 2px; }
.rule-body th { padding: 0.6em 1em; text-align: left; font-weight: 700; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.05em; }
.rule-body td { padding: 0.6em 1em; vertical-align: top; }
.rule-body tbody tr:not(:last-child) { border-bottom-width: 1px; }

/* Dark — inherit from legal-dark */
.legal-dark .rule-body, .rule-body.legal-dark { color: #a1a1aa; }
.legal-dark .rule-body h1, .rule-body.legal-dark h1,
.legal-dark .rule-body h2, .rule-body.legal-dark h2,
.legal-dark .rule-body h3, .rule-body.legal-dark h3,
.legal-dark .rule-body h4, .rule-body.legal-dark h4 { color: #f4f4f5; }
.rule-body.legal-dark h2 { border-bottom-color: #27272a; }
.rule-body.legal-dark strong { color: #f4f4f5; }
.rule-body.legal-dark code { background: #27272a; color: #a5f3fc; }
.rule-body.legal-dark a { color: #60a5fa; }
.rule-body.legal-dark blockquote { border-left-color: #3f3f46; background: #18181b; color: #71717a; }
.rule-body.legal-dark hr { border-top-color: #27272a; }
.rule-body.legal-dark table { border: 1px solid #27272a; }
.rule-body.legal-dark thead { border-bottom-color: #3f3f46; background: #18181b; }
.rule-body.legal-dark th { color: #a1a1aa; }
.rule-body.legal-dark td { color: #d4d4d8; }
.rule-body.legal-dark tbody tr:not(:last-child) { border-bottom-color: #27272a; }
.rule-body.legal-dark tbody tr:hover { background: #18181b; }

/* Light */
.rule-body.legal-light { color: #374151; }
.rule-body.legal-light h1, .rule-body.legal-light h2, .rule-body.legal-light h3, .rule-body.legal-light h4 { color: #0f172a; }
.rule-body.legal-light h2 { border-bottom-color: #e2e8f0; }
.rule-body.legal-light strong { color: #0f172a; }
.rule-body.legal-light code { background: #f1f5f9; color: #0f766e; border: 1px solid #e2e8f0; }
.rule-body.legal-light a { color: #2563eb; }
.rule-body.legal-light blockquote { border-left-color: #cbd5e1; background: #f8fafc; color: #64748b; }
.rule-body.legal-light hr { border-top-color: #e2e8f0; }
.rule-body.legal-light table { border: 1px solid #e2e8f0; }
.rule-body.legal-light thead { border-bottom-color: #cbd5e1; background: #f8fafc; }
.rule-body.legal-light th { color: #64748b; }
.rule-body.legal-light td { color: #374151; }
.rule-body.legal-light tbody tr:not(:last-child) { border-bottom-color: #f1f5f9; }
.rule-body.legal-light tbody tr:hover { background: #fafafa; }
</style>
