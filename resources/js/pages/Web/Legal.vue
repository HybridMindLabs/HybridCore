<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { FileText, Shield, Cookie, Scale, Clock } from '@lucide/vue';
import { marked } from 'marked';

const props = defineProps<{
    slug: string;
    title: string;
    subtitle: string;
    content: string;
    updated_at: string;
    allPages?: { slug: string; title: string }[];
}>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const icons: Record<string, unknown> = { terms: FileText, privacy: Shield, cookies: Cookie };
const icon = computed(() => icons[props.slug] ?? Scale);

const parsedContent = ref('');

interface TocEntry { id: string; text: string; level: number }
const toc = ref<TocEntry[]>([]);
const activeId = ref('');

onMounted(async () => {
    marked.setOptions({ breaks: true });

    // Custom renderer to add IDs to headings
    const renderer = new marked.Renderer();
    const entries: TocEntry[] = [];

    renderer.heading = ({ text, depth }: { text: string; depth: number }) => {
        const id = text.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        if (depth <= 3) entries.push({ id, text, level: depth });
        return `<h${depth} id="${id}">${text}</h${depth}>`;
    };

    parsedContent.value = await marked.parse(props.content, { renderer }) as string;
    toc.value = entries;

    await nextTick();

    // Intersection observer for active TOC highlight
    const observer = new IntersectionObserver(
        (obs) => {
            for (const entry of obs) {
                if (entry.isIntersecting) activeId.value = entry.target.id;
            }
        },
        { rootMargin: '-20% 0px -70% 0px' },
    );
    document.querySelectorAll('.legal-body h1, .legal-body h2, .legal-body h3').forEach(el => observer.observe(el));
});

const formattedDate = computed(() => {
    try {
        return new Date(props.updated_at).toLocaleDateString('en-GB', {
            day: 'numeric', month: 'long', year: 'numeric',
        });
    } catch { return props.updated_at; }
});

const navPages = computed(() => props.allPages ?? [
    { slug: 'terms',   title: 'Terms of Service' },
    { slug: 'privacy', title: 'Privacy Policy' },
    { slug: 'cookies', title: 'Cookie Policy' },
]);

function scrollTo(id: string) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head :title="title" />

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
                <Breadcrumb :items="[{ label: 'Home', href: '/' }, { label: 'Legal' }, { label: title }]" />

                <div class="max-w-2xl">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                        :class="dark ? 'border-blue-500/25 bg-blue-500/8 text-blue-400' : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                    >
                        <component :is="icon" :size="11" :stroke-width="2.2" />
                        Legal
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ title }}
                    </h1>

                    <p
                        v-if="subtitle"
                        class="mt-4 text-[15px] leading-relaxed max-w-lg"
                        :class="dark ? 'text-zinc-400' : 'text-zinc-500'"
                    >{{ subtitle }}</p>

                    <p class="flex items-center gap-1.5 mt-4 text-[12px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                        <Clock :size="12" :stroke-width="1.8" />
                        Last updated {{ formattedDate }}
                    </p>

                    <!-- Page switcher -->
                    <nav class="flex flex-wrap gap-2 mt-6">
                        <Link
                            v-for="p in navPages"
                            :key="p.slug"
                            :href="`/legal/${p.slug}`"
                            class="px-4 py-1.5 rounded-full text-[13px] font-medium border transition-all"
                            :class="p.slug === slug
                                ? (dark ? 'bg-blue-500/12 text-blue-400 border-blue-500/25 font-semibold' : 'bg-blue-50 text-blue-600 border-blue-200 font-semibold')
                                : (dark ? 'text-zinc-500 border-zinc-800 hover:border-zinc-600 hover:text-zinc-300' : 'text-zinc-500 border-zinc-200 hover:border-zinc-400 hover:text-zinc-700')"
                        >{{ p.title }}</Link>
                    </nav>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <!-- ── Body ──────────────────────────────────────────────── -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div class="flex gap-6 items-start">

                <!-- TOC sidebar -->
                <aside
                    v-if="toc.length"
                    class="hidden lg:block w-60 xl:w-64 shrink-0 sticky top-24 self-start"
                >
                    <div class="rounded-2xl border overflow-hidden"
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
                            class="legal-body p-6 sm:p-8"
                            :class="dark ? 'legal-dark' : 'legal-light'"
                            v-html="parsedContent"
                        />
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
.legal-dark blockquote { border-left-color: #3f3f46; background: #18181b; color: #71717a; }
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
.legal-light blockquote { border-left-color: #cbd5e1; background: #f8fafc; color: #64748b; }
.legal-light hr { border-top-color: #e2e8f0; }
.legal-light table { border: 1px solid #e2e8f0; }
.legal-light thead { border-bottom-color: #cbd5e1; background: #f8fafc; }
.legal-light th { color: #64748b; }
.legal-light td { color: #374151; }
.legal-light tbody tr:not(:last-child) { border-bottom-color: #f1f5f9; }
.legal-light tbody tr:hover { background: #fafafa; }
</style>
