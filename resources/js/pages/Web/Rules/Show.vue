<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { BookOpen, Clock, ArrowLeft, ArrowRight } from '@lucide/vue';
interface Rule {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    /** Already HTML — the browser used to parse the markdown itself. */
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

interface TocEntry { id: string; text: string; level: number }

const props = defineProps<{
    rule: Rule;
    allRules: RuleNav[];
    toc: TocEntry[];
    reading_minutes: number;
    seo: Seo;
}>();

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');

const parsedContent = computed(() => props.rule.content ?? '');

/** Highlights the section being read. Ids now come from the server. */
const activeId = ref('');
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!props.toc.length) return;

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) activeId.value = entry.target.id;
            }
        },
        { rootMargin: '-20% 0px -70% 0px' },
    );

    props.toc.forEach((entry) => {
        const el = document.getElementById(entry.id);
        if (el) observer!.observe(el);
    });
});

onBeforeUnmount(() => observer?.disconnect());

const formattedDate = computed(() => formatDate(props.rule.updated_at, { dateStyle: 'long' }));
const readingMinutes = computed(() => props.reading_minutes);

const ruleIndex = computed(() => props.allRules.findIndex(r => r.slug === props.rule.slug));

const shortDate = computed(() => formatDate(props.rule.updated_at, { day: 'numeric', month: 'short' }));

const canonicalUrl = computed(() => route('rules.show', props.rule.slug));

const prevRule = computed(() => ruleIndex.value > 0 ? props.allRules[ruleIndex.value - 1] : null);
const nextRule = computed(() => ruleIndex.value < props.allRules.length - 1 ? props.allRules[ruleIndex.value + 1] : null);
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
        <link rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
        <meta property="og:url" :content="canonicalUrl" />
        <meta property="article:modified_time" :content="rule.updated_at" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="seo.title" />
        <meta name="twitter:description" :content="seo.description" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
        >
            <!-- Glows + dot grid (same as Home) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <div class="hc-hero-glow absolute -top-32 left-1/4 w-[520px] h-[380px] rounded-full blur-[120px]"
                    :class="dark ? 'bg-blue-500/8' : 'bg-blue-400/10'" />
                <div class="hc-hero-glow hc-hero-glow--slow absolute -top-16 right-1/4 w-[320px] h-[300px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/6' : 'bg-violet-400/8'" />
                <div class="absolute inset-0" :class="dark ? 'opacity-50' : 'opacity-[0.35]'"
                    :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 sm:py-12">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    { label: t('rules.title'), href: route('rules.index') },
                    { label: rule.title },
                ]" />

                <div class="grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,300px)] lg:items-end mt-4">

                    <div class="max-w-2xl">
                        <div class="hc-hero-in flex items-center gap-2 flex-wrap">
                            <span
                                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                                :class="dark ? 'border-blue-500/25 bg-blue-500/10 text-blue-400' : 'border-blue-500/30 bg-blue-500/10 text-blue-700'"
                            >
                                <BookOpen :size="11" :stroke-width="2.2" aria-hidden="true" />
                                {{ t('rules.rule_number', { number: ruleIndex + 1, total: allRules.length }) }}
                            </span>
                        </div>

                        <h1 class="hc-hero-in hc-hero-in--1 mt-4 text-[30px] sm:text-[40px] font-black tracking-tight leading-[1.07]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ rule.title }}
                        </h1>

                        <p
                            v-if="rule.excerpt"
                            class="hc-hero-in hc-hero-in--2 mt-3 text-[15px] leading-relaxed max-w-lg"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-500'"
                        >{{ rule.excerpt }}</p>

                        <div class="hc-hero-in hc-hero-in--3 flex items-center gap-2.5 mt-6 flex-wrap">
                            <Link :href="route('rules.index')"
                                class="group inline-flex items-center gap-2 font-bold text-[13.5px] px-5 py-2.5 rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-500 hover:border-zinc-400 hover:bg-white'">
                                <ArrowLeft :size="14" :stroke-width="2.2" aria-hidden="true"
                                    class="transition-transform group-hover:-translate-x-0.5" />
                                {{ t('rules.back_to_rules') }}
                            </Link>
                            <Link v-if="nextRule" :href="route('rules.show', nextRule.slug)"
                                class="group inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-[13.5px] px-5 py-2.5 rounded-xl transition shadow-md shadow-blue-600/25 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                {{ t('rules.next_rule') }}
                                <ArrowRight :size="14" :stroke-width="2.2" aria-hidden="true"
                                    class="transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        </div>
                    </div>

                    <!-- Where you are in the set, and what this one costs to read. -->
                    <div class="hc-hero-in hc-hero-in--2 rounded-2xl border px-4 py-4 backdrop-blur-md"
                        :class="dark
                            ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                            : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'">

                        <div class="flex items-center justify-between gap-3 mb-2">
                            <span class="text-[10px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('rules.progress') }}</span>
                            <span class="text-[11px] font-bold tabular-nums"
                                :class="dark ? 'text-zinc-300' : 'text-zinc-500'">{{ ruleIndex + 1 }} / {{ allRules.length }}</span>
                        </div>

                        <!-- One segment per rule; the ones you have passed stay filled -->
                        <div class="flex items-center gap-1" role="progressbar"
                            :aria-valuenow="ruleIndex + 1" aria-valuemin="1" :aria-valuemax="allRules.length"
                            :aria-label="t('rules.rule_number', { number: ruleIndex + 1, total: allRules.length })">
                            <span v-for="(r, i) in allRules" :key="r.id"
                                class="hc-hero-bar h-1.5 flex-1 rounded-full transition-colors"
                                :class="i <= ruleIndex
                                    ? 'bg-blue-500'
                                    : dark ? 'bg-zinc-800' : 'bg-zinc-200'"
                                aria-hidden="true" />
                        </div>

                        <dl class="grid grid-cols-2 gap-3 mt-4 pt-3.5 border-t"
                            :class="dark ? 'border-zinc-800' : 'border-zinc-200'">
                            <div :title="t('rules.reading_time_hint')">
                                <dt class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                    <Clock :size="10" :stroke-width="2.2" aria-hidden="true" />
                                    {{ t('rules.reading_label') }}
                                </dt>
                                <dd class="text-[15px] font-black mt-1"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                                    {{ t('rules.reading_time', { m: readingMinutes }) }}
                                </dd>
                            </div>
                            <div :title="t('rules.updated_hint')">
                                <dt class="text-[10px] font-bold uppercase tracking-widest"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('rules.stat_updated') }}</dt>
                                <dd class="text-[15px] font-black mt-1"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ shortDate }}</dd>
                            </div>
                        </dl>
                    </div>
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
                        >{{ t('rules.back_to_rules') }}</p>
                        <nav class="flex flex-col p-2 gap-0.5" :aria-label="t('rules.title')">
                            <Link
                                v-for="(r, i) in allRules"
                                :key="r.slug"
                                :href="route('rules.show', r.slug)"
                                class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="r.slug === rule.slug
                                    ? (dark ? 'bg-blue-500/15 text-blue-400 font-semibold' : 'bg-blue-500/10 text-blue-700 font-semibold')
                                    : (dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-white/[0.04]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100')"
                                :aria-current="r.slug === rule.slug ? 'page' : undefined"
                            >
                                <span class="text-[10px] font-black tabular-nums w-5 shrink-0"
                                    :class="r.slug === rule.slug
                                        ? (dark ? 'text-blue-400' : 'text-blue-600')
                                        : (dark ? 'text-zinc-500' : 'text-zinc-400')"
                                    aria-hidden="true"
                                >{{ i + 1 }}</span>
                                <span class="leading-snug truncate">{{ r.title }}</span>
                            </Link>
                        </nav>
                    </div>

                    <!-- TOC for current rule -->
                    <div v-if="toc.length" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <p class="px-4 py-3 border-b text-[11px] font-black uppercase tracking-widest"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-500' : 'border-zinc-100 bg-zinc-50 text-zinc-400'"
                        >{{ t('rules.on_this_page') }}</p>
                        <!-- Real anchors, not buttons: these are now linkable,
                             copyable and crawlable, and keyboard users get the
                             same behaviour for free. -->
                        <nav class="flex flex-col p-2 gap-0.5" :aria-label="t('rules.on_this_page')">
                            <a
                                v-for="entry in toc"
                                :key="entry.id"
                                :href="`#${entry.id}`"
                                class="text-left text-[13px] py-1.5 transition-colors truncate border-l-2 px-3 rounded-r focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="[
                                    entry.level === 3 ? 'pl-6 text-[12px]' : '',
                                    activeId === entry.id
                                        ? (dark ? 'border-blue-500 text-blue-400' : 'border-blue-500 text-blue-700')
                                        : (dark ? 'border-transparent text-zinc-500 hover:text-zinc-200' : 'border-transparent text-zinc-500 hover:text-zinc-900'),
                                ]"
                                :aria-current="activeId === entry.id ? 'location' : undefined"
                            >{{ entry.text }}</a>
                        </nav>
                    </div>
                </aside>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <article class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div
                            class="rule-body p-6 sm:p-8"
                            :class="dark ? 'legal-dark' : 'legal-light'"
                            v-html="parsedContent"
                        />
                    </article>

                    <!-- Prev / next rule -->
                    <nav class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4" :aria-label="t('rules.pager_label')">
                        <Link
                            v-if="prevRule"
                            :href="route('rules.show', prevRule.slug)"
                            class="group flex items-center gap-3 rounded-2xl border px-4 py-3.5 transition-all hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-blue-500/40' : 'border-zinc-200 bg-white hover:border-blue-400/50 shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-md'"
                        >
                            <ArrowLeft :size="15" :stroke-width="2" aria-hidden="true"
                                class="shrink-0 transition-transform group-hover:-translate-x-1"
                                :class="dark ? 'text-zinc-500 group-hover:text-blue-400' : 'text-zinc-500 group-hover:text-blue-600'" />
                            <span class="min-w-0">
                                <span class="block text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('rules.previous_rule') }}</span>
                                <span class="block text-[13.5px] font-bold truncate transition-colors"
                                    :class="dark ? 'text-zinc-200 group-hover:text-blue-400' : 'text-zinc-900 group-hover:text-blue-700'">{{ prevRule.title }}</span>
                            </span>
                        </Link>
                        <span v-else class="hidden sm:block" />
                        <Link
                            v-if="nextRule"
                            :href="route('rules.show', nextRule.slug)"
                            class="group flex items-center justify-end gap-3 rounded-2xl border px-4 py-3.5 text-right transition-all hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                            :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-blue-500/40' : 'border-zinc-200 bg-white hover:border-blue-400/50 shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-md'"
                        >
                            <span class="min-w-0">
                                <span class="block text-[10px] font-bold uppercase tracking-widest" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('rules.next_rule') }}</span>
                                <span class="block text-[13.5px] font-bold truncate transition-colors"
                                    :class="dark ? 'text-zinc-200 group-hover:text-blue-400' : 'text-zinc-900 group-hover:text-blue-700'">{{ nextRule.title }}</span>
                            </span>
                            <ArrowRight :size="15" :stroke-width="2" aria-hidden="true"
                                class="shrink-0 transition-transform group-hover:translate-x-1"
                                :class="dark ? 'text-zinc-500 group-hover:text-blue-400' : 'text-zinc-500 group-hover:text-blue-600'" />
                        </Link>
                    </nav>

                    <!-- Mobile rule switcher -->
                    <nav class="mt-8 lg:hidden" :aria-label="t('rules.other_rules')">
                        <p class="text-[11px] font-black uppercase tracking-widest mb-3" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('rules.other_rules') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="r in allRules"
                                :key="r.slug"
                                :href="route('rules.show', r.slug)"
                                class="px-3 py-1.5 rounded-full text-[13px] border transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="r.slug === rule.slug
                                    ? (dark ? 'bg-blue-500/15 text-blue-400 border-blue-500/25 font-semibold' : 'bg-blue-500/10 text-blue-700 border-blue-500/25 font-semibold')
                                    : (dark ? 'text-zinc-400 border-zinc-800 hover:border-zinc-600 hover:text-zinc-100' : 'text-zinc-500 border-zinc-300 hover:border-zinc-400 hover:text-zinc-900')"
                                :aria-current="r.slug === rule.slug ? 'page' : undefined"
                            >{{ r.title }}</Link>
                        </div>
                    </nav>
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
