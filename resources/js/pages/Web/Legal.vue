<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { ArrowRight, Clock, Cookie, FileText, History, List, Scale, Shield } from '@lucide/vue';

interface TocEntry { id: string; text: string; level: number }

const props = defineProps<{
    slug: string;
    title: string;
    subtitle: string;
    /**
     * Already HTML. It used to arrive as markdown and be parsed in the browser
     * with `marked`, which meant the legal text — the content most in need of
     * being readable without scripts — only appeared after JS ran.
     */
    content: string;
    toc: TocEntry[];
    reading_minutes: number;
    updated_at: string;
    allPages?: { slug: string; title: string }[];
    canonical: string;
}>();

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');

const icons: Record<string, unknown> = { terms: FileText, privacy: Shield, cookies: Cookie };
const icon = computed(() => icons[props.slug] ?? Scale);

const formattedDate = computed(() => formatDate(props.updated_at, { dateStyle: 'long' }));

const navPages = computed(() => props.allPages ?? []);
/** The switcher lists where you can go, so the page you are on is left out. */
const otherPages = computed(() => navPages.value.filter((p) => p.slug !== props.slug));

const sectionsLabel = computed(() =>
    props.toc.length === 1 ? t('legal.sections_one') : t('legal.sections_many', { count: props.toc.length }),
);

const firstSectionHref = computed(() => (props.toc.length ? `#${props.toc[0].id}` : '#'));

function pageHref(slug: string) {
    return `/legal/${slug}`;
}

// Built here so the template stays free of backtick interpolation.
const dotGrid = computed(() => {
    const dot = dark.value ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)';

    return `background-image:radial-gradient(circle,${dot} 1px,transparent 1px);background-size:28px 28px`;
});

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
</script>

<template>
    <Head>
        <title>{{ title }}</title>
        <meta name="description" :content="subtitle || t('legal.meta_fallback')" />
        <link rel="canonical" :href="canonical" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <section
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
            :aria-label="t('legal.breadcrumb')"
        >
            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <div class="hc-hero-glow absolute -top-32 left-1/4 w-[520px] h-[380px] rounded-full blur-[120px]"
                    :class="dark ? 'bg-blue-500/8' : 'bg-blue-400/10'" />
                <div class="hc-hero-glow hc-hero-glow--slow absolute -top-16 right-1/4 w-[320px] h-[300px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/6' : 'bg-violet-400/8'" />
                <!-- The grid was dark-only, so the light theme lost the texture
                     every other page has. -->
                <div class="absolute inset-0" :class="dark ? 'opacity-50' : 'opacity-[0.35]'"
                    :style="dotGrid" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-8 sm:py-12">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    { label: t('legal.breadcrumb') },
                    { label: title },
                ]" />

                <div class="grid gap-8 lg:gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,340px)] lg:items-end mt-4">

                    <div class="max-w-xl">
                        <div class="hc-hero-in inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                            :class="dark ? 'border-blue-500/25 bg-blue-500/10 text-blue-400' : 'border-blue-500/30 bg-blue-500/10 text-blue-700'">
                            <component :is="icon" :size="11" :stroke-width="2.2" aria-hidden="true" />
                            {{ t('legal.eyebrow') }}
                        </div>

                        <h1 class="hc-hero-in hc-hero-in--1 mt-4 text-[30px] sm:text-[40px] font-black tracking-tight leading-[1.07]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ title }}
                        </h1>

                        <p v-if="subtitle" class="hc-hero-in hc-hero-in--2 mt-3 text-[15px] leading-relaxed max-w-lg"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                            {{ subtitle }}
                        </p>

                        <div class="hc-hero-in hc-hero-in--3 flex items-center gap-2.5 mt-6 flex-wrap">
                            <a v-if="toc.length" :href="firstSectionHref"
                                class="group inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-[13.5px] px-5 py-2.5 rounded-xl transition shadow-md shadow-blue-600/25 hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                {{ t('legal.jump_first') }}
                                <ArrowRight :size="14" :stroke-width="2.2" aria-hidden="true"
                                    class="transition-transform group-hover:translate-x-0.5" />
                            </a>
                            <Link :href="route('contact.show')"
                                class="inline-flex items-center font-bold text-[13.5px] px-5 py-2.5 rounded-xl border transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-white'">
                                {{ t('legal.ask_us') }}
                            </Link>
                        </div>
                    </div>

                    <!-- The other policies, as a real block. They used to be a
                         row of pills wedged under the text, leaving the whole
                         right side of the hero empty. -->
                    <div v-if="otherPages.length" class="hc-hero-in hc-hero-in--2">
                        <p class="text-[10px] font-bold uppercase tracking-widest mb-2.5"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('legal.switcher_title') }}</p>
                        <div class="flex flex-col gap-2">
                            <Link v-for="(p, i) in otherPages" :key="p.slug" :href="pageHref(p.slug)"
                                class="hc-reveal hc-card-hover group flex items-center justify-between gap-3 rounded-xl border px-4 py-3 backdrop-blur-md transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                                :style="{ animationDelay: 0.18 + i * 0.06 + 's' }"
                                :class="dark
                                    ? 'border-zinc-700/70 bg-zinc-900/85 shadow-lg shadow-black/30'
                                    : 'border-zinc-300 bg-white shadow-[0_4px_16px_rgba(0,0,0,0.10)]'">
                                <span class="text-[13px] font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ p.title }}</span>
                                <ArrowRight :size="13" :stroke-width="2.2" aria-hidden="true"
                                    class="shrink-0 transition-transform group-hover:translate-x-0.5"
                                    :class="dark ? 'text-zinc-500' : 'text-zinc-400'" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- What a reader wants before committing: how long, how many
                     parts, how fresh. -->
                <dl class="hc-hero-in hc-hero-in--4 flex flex-wrap items-center gap-x-5 gap-y-2 mt-7 text-[12.5px]"
                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    <div class="flex items-center gap-1.5">
                        <Clock :size="12" :stroke-width="1.9" aria-hidden="true" />
                        <dt class="sr-only">{{ t('legal.reading_time', { m: reading_minutes }) }}</dt>
                        <dd>{{ t('legal.reading_time', { m: reading_minutes }) }}</dd>
                    </div>
                    <div v-if="toc.length" class="flex items-center gap-1.5">
                        <List :size="12" :stroke-width="1.9" aria-hidden="true" />
                        <dt class="sr-only">{{ sectionsLabel }}</dt>
                        <dd>{{ sectionsLabel }}</dd>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <History :size="12" :stroke-width="1.9" aria-hidden="true" />
                        <dt class="sr-only">{{ t('legal.updated_short') }}</dt>
                        <dd><time :datetime="updated_at">{{ t('legal.last_updated', { date: formattedDate }) }}</time></dd>
                    </div>
                </dl>
            </div>
        </section>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div class="flex gap-6 items-start">

                <!-- Contents. Anchors, not buttons: a section is now something
                     you can link to, open in a new tab and reach by keyboard. -->
                <aside v-if="toc.length" class="hidden lg:block w-60 xl:w-64 shrink-0 sticky top-24 self-start">
                    <nav class="hc-reveal rounded-2xl border overflow-hidden" :aria-label="t('legal.toc_title')"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <p class="px-4 py-3 border-b text-[11px] font-black uppercase tracking-widest"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e] text-zinc-400' : 'border-zinc-100 bg-zinc-50 text-zinc-500'">
                            {{ t('legal.toc_title') }}
                        </p>
                        <ol class="flex flex-col p-2 gap-0.5">
                            <li v-for="entry in toc" :key="entry.id">
                                <a :href="`#${entry.id}`"
                                    :aria-current="activeId === entry.id ? 'true' : undefined"
                                    class="block text-left text-[13px] py-1.5 transition-colors truncate border-l-2 px-3 rounded-r
                                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                    :class="[
                                        entry.level === 3 ? 'pl-6 text-[12px]' : '',
                                        activeId === entry.id
                                            ? dark ? 'border-blue-500 text-blue-400' : 'border-blue-500 text-blue-700'
                                            : dark ? 'border-transparent text-zinc-400 hover:text-zinc-200' : 'border-transparent text-zinc-600 hover:text-zinc-900',
                                    ]"
                                >{{ entry.text }}</a>
                            </li>
                        </ol>
                    </nav>
                </aside>

                <div class="flex-1 min-w-0">
                    <!-- Collapsed contents for narrow screens, which had none
                         at all — the sidebar was hidden below lg. -->
                    <details v-if="toc.length" class="lg:hidden mb-4 rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <summary class="px-4 py-3 text-[12px] font-bold uppercase tracking-widest cursor-pointer"
                            :class="dark ? 'text-zinc-300' : 'text-zinc-700'">
                            {{ t('legal.toc_toggle') }}
                        </summary>
                        <ol class="flex flex-col p-2 gap-0.5 border-t"
                            :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                            <li v-for="entry in toc" :key="entry.id">
                                <a :href="`#${entry.id}`"
                                    class="block text-[13px] py-1.5 px-3 rounded transition-colors"
                                    :class="[
                                        entry.level === 3 ? 'pl-6 text-[12px]' : '',
                                        dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-600 hover:text-zinc-900',
                                    ]"
                                >{{ entry.text }}</a>
                            </li>
                        </ol>
                    </details>

                    <div class="hc-reveal rounded-2xl border overflow-hidden" style="animation-delay:0.08s"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div v-if="content" class="legal-body p-6 sm:p-8" :class="dark ? 'legal-dark' : 'legal-light'" v-html="content" />
                        <p v-else class="p-8 text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('legal.empty') }}
                        </p>
                    </div>
                </div>

            </div>
        </div>

    </PublicLayout>
</template>

<style>
.legal-body {
    font-size: 0.9375rem;
    line-height: 1.8;
    /* Legal text is dense; an unbounded measure made it much harder to read. */
    max-width: 72ch;
}
.legal-body h1, .legal-body h2, .legal-body h3, .legal-body h4 {
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-top: 2.25em;
    margin-bottom: 0.6em;
    line-height: 1.3;
    scroll-margin-top: 6rem;
}
.legal-body h2 { font-size: 1.2rem; padding-bottom: 0.5em; border-bottom-width: 1px; }
.legal-body h3 { font-size: 1.05rem; }
.legal-body > h2:first-child, .legal-body > h3:first-child { margin-top: 0; }
.legal-body p { margin-bottom: 1.1em; }
.legal-body ul, .legal-body ol { padding-left: 1.5em; margin-bottom: 1.1em; }
.legal-body ul { list-style-type: disc; }
.legal-body ol { list-style-type: decimal; }
.legal-body li { margin-bottom: 0.35em; }
.legal-body strong { font-weight: 700; }
.legal-body code {
    font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace;
    font-size: 0.82em;
    padding: 0.15em 0.4em;
    border-radius: 4px;
}
.legal-body a { text-decoration: underline; text-underline-offset: 2px; transition: opacity 0.15s; }
.legal-body a:hover { opacity: 0.75; }
.legal-body blockquote {
    border-left-width: 3px;
    padding: 0.75em 1.25em;
    border-radius: 0 6px 6px 0;
    margin: 1.25em 0;
    font-style: italic;
}
.legal-body hr { margin: 2em 0; border-top-width: 1px; }
.legal-body table {
    width: 100%;
    border-collapse: collapse;
    margin: 1.5em 0;
    font-size: 0.875em;
    border-radius: 8px;
    overflow: hidden;
}
.legal-body thead { border-bottom-width: 2px; }
.legal-body th {
    padding: 0.6em 1em;
    text-align: left;
    font-weight: 700;
    font-size: 0.8em;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.legal-body td { padding: 0.6em 1em; vertical-align: top; }
.legal-body tbody tr:not(:last-child) { border-bottom-width: 1px; }

/* Dark */
.legal-dark { color: #a1a1aa; }
.legal-dark h1, .legal-dark h2, .legal-dark h3, .legal-dark h4 { color: #f4f4f5; }
.legal-dark h2 { border-bottom-color: #27272a; }
.legal-dark strong { color: #f4f4f5; }
.legal-dark code { background: #27272a; color: #a5f3fc; }
.legal-dark a { color: #60a5fa; }
.legal-dark blockquote { border-left-color: #3f3f46; background: #18181b; color: #a1a1aa; }
.legal-dark hr { border-top-color: #27272a; }
.legal-dark table { border: 1px solid #27272a; }
.legal-dark thead { border-bottom-color: #3f3f46; background: #18181b; }
.legal-dark th { color: #a1a1aa; }
.legal-dark td { color: #d4d4d8; }
.legal-dark tbody tr:not(:last-child) { border-bottom-color: #27272a; }
.legal-dark tbody tr:hover { background: #18181b; }

/* Light */
.legal-light { color: #374151; }
.legal-light h1, .legal-light h2, .legal-light h3, .legal-light h4 { color: #0f172a; }
.legal-light h2 { border-bottom-color: #e2e8f0; }
.legal-light strong { color: #0f172a; }
.legal-light code { background: #f1f5f9; color: #0f766e; border: 1px solid #e2e8f0; }
.legal-light a { color: #2563eb; }
.legal-light blockquote { border-left-color: #cbd5e1; background: #f8fafc; color: #475569; }
.legal-light hr { border-top-color: #e2e8f0; }
.legal-light table { border: 1px solid #e2e8f0; }
.legal-light thead { border-bottom-color: #cbd5e1; background: #f8fafc; }
.legal-light th { color: #475569; }
.legal-light td { color: #374151; }
.legal-light tbody tr:not(:last-child) { border-bottom-color: #f1f5f9; }
.legal-light tbody tr:hover { background: #fafafa; }
</style>
