<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUp, ChevronLeft, ChevronRight, Tag } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import ArticleComments from '@/components/News/ArticleComments.vue';
import ArticleHero from '@/components/News/ArticleHero.vue';
import ArticleSidebar from '@/components/News/ArticleSidebar.vue';
import type { Article, ArticleSummary, CommentPage } from '@/components/News/articleTypes';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';

const props = defineProps<{
    article: Article;
    related: ArticleSummary[];
    prev: { id: number; title: string; slug: string } | null;
    next: { id: number; title: string; slug: string } | null;
    comments: CommentPage;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

/**
 * Structured data for search engines. Titles and names are written by staff, so
 * "<" is escaped: text that closes the script tag must not reach the markup.
 */
const jsonLd = computed(() => JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'NewsArticle',
    headline: props.article.title,
    description: props.article.meta_description ?? undefined,
    image: props.article.og_image_url ?? props.article.featured_image_url ?? undefined,
    datePublished: props.article.published_at_iso,
    author: props.article.author ? { '@type': 'Person', name: props.article.author.name } : undefined,
    articleSection: props.article.category?.name,
    wordCount: props.article.word_count,
    mainEntityOfPage: props.article.canonical,
}).replace(/</g, '\\u003c'));

// ── Reading progress + back to top ───────────────────────────────
const readingProgress = ref(0);
const showBackToTop = ref(false);
const articleEl = ref<HTMLElement | null>(null);

function updateProgress() {
    const el = articleEl.value;
    if (!el) return;

    // Shown once scrolling back up by hand stops being the fastest way — about one viewport in.
    showBackToTop.value = window.scrollY > window.innerHeight;

    const start = el.offsetTop;
    const scrollable = el.offsetHeight - window.innerHeight;
    readingProgress.value = scrollable <= 0
        ? (window.scrollY > start ? 100 : 0)
        : Math.min(100, Math.max(0, Math.round(((window.scrollY - start) / scrollable) * 100)));
}

onMounted(() => window.addEventListener('scroll', updateProgress, { passive: true }));
onBeforeUnmount(() => window.removeEventListener('scroll', updateProgress));

function backToTop() {
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
}

const navCard = computed(() => dark.value
    ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
    : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm');
</script>

<template>
    <Head>
        <title>{{ article.meta_title }}</title>
        <meta name="description" :content="article.meta_description ?? ''" />
        <meta property="og:title" :content="article.meta_title" />
        <meta property="og:description" :content="article.meta_description ?? ''" />
        <meta v-if="article.og_image_url" property="og:image" :content="article.og_image_url" />
        <meta property="og:type" content="article" />
        <meta property="og:url" :content="article.canonical" />
        <meta name="twitter:card" :content="article.og_image_url ? 'summary_large_image' : 'summary'" />
        <link rel="canonical" :href="article.canonical" />
        <link rel="alternate" type="application/rss+xml" :title="t('news.page_title')" :href="route('news.feed')" />
        <component :is="'script'" type="application/ld+json" v-html="jsonLd" />
    </Head>

    <PublicLayout>
        <div class="fixed top-0 left-0 right-0 h-0.5 z-50 pointer-events-none">
            <div class="h-full bg-blue-500 transition-[width] duration-150 ease-out"
                role="progressbar" :aria-label="t('news.reading_progress')"
                :aria-valuenow="readingProgress" aria-valuemin="0" aria-valuemax="100"
                :style="{ width: readingProgress + '%' }" />
        </div>

        <Transition
            enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-2">
            <button v-if="showBackToTop" type="button"
                class="fixed bottom-6 right-6 z-40 w-10 h-10 rounded-full border flex items-center justify-center shadow-lg backdrop-blur-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="dark ? 'border-zinc-700/70 bg-zinc-900/85 text-zinc-300 hover:text-white hover:border-zinc-600' : 'border-zinc-200 bg-white/90 text-zinc-500 hover:text-zinc-900 hover:border-zinc-300'"
                :aria-label="t('news.back_to_top')" :title="t('news.back_to_top')"
                @click="backToTop">
                <ArrowUp :size="16" :stroke-width="2.2" aria-hidden="true" />
            </button>
        </Transition>

        <ArticleHero :article="article" :comment-count="comments.total" />

        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-10">
            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_260px] gap-10 items-start">
                <!-- Article and its comments are one column; the sidebar the other. -->
                <div class="min-w-0">
                    <article ref="articleEl">
                        <!-- Capped measure (~70 characters a line); headings already carry ids from the server. -->
                        <div class="prose max-w-[68ch] prose-headings:scroll-mt-24"
                            :class="dark
                                ? 'prose-invert prose-zinc prose-headings:text-zinc-100 prose-headings:font-black prose-p:text-zinc-300 prose-a:text-blue-400 prose-strong:text-zinc-100 prose-code:text-blue-300 prose-code:bg-zinc-900/60 prose-code:text-[13px] prose-pre:bg-[#0d0d0f] prose-pre:border prose-pre:border-zinc-800/70 prose-blockquote:border-l-blue-500/50 prose-blockquote:text-zinc-400 prose-blockquote:bg-zinc-900/30 prose-hr:border-zinc-800/70 prose-img:rounded-xl prose-img:border prose-img:border-zinc-800/70'
                                : 'prose-zinc prose-headings:font-black prose-a:text-blue-600 prose-code:bg-zinc-100 prose-pre:bg-zinc-50 prose-pre:border prose-pre:border-zinc-200 prose-blockquote:border-l-blue-400 prose-img:rounded-xl prose-img:border prose-img:border-zinc-200'"
                            v-html="article.body" />

                        <div v-if="article.tags.length" class="flex flex-wrap gap-2 mt-10 pt-7 border-t" :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                            <Link v-for="tag in article.tags" :key="tag.slug" :href="route('news.tag', tag.slug)"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[12px] font-semibold transition"
                                :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                                <Tag :size="10" :stroke-width="2" aria-hidden="true" />#{{ tag.name }}
                            </Link>
                        </div>

                        <nav v-if="prev || next" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-8 pt-7 border-t" :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'"
                            :aria-label="`${t('news.prev_article')} / ${t('news.next_article')}`">
                            <Link v-if="prev" :href="route('news.show', prev.slug)" rel="prev"
                                class="flex flex-col gap-1.5 p-4 rounded-xl border transition group" :class="navCard">
                                <span class="text-[11px] font-bold uppercase tracking-widest flex items-center gap-1 text-zinc-500">
                                    <ChevronLeft :size="11" :stroke-width="2.2" aria-hidden="true" /> {{ t('news.prev_article') }}
                                </span>
                                <span class="text-[13px] font-semibold line-clamp-2 transition-colors"
                                    :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-500 group-hover:text-blue-600'">{{ prev.title }}</span>
                            </Link>
                            <Link v-if="next" :href="route('news.show', next.slug)" rel="next"
                                class="flex flex-col gap-1.5 p-4 rounded-xl border transition group text-right" :class="[navCard, !prev ? 'sm:col-start-2' : '']">
                                <span class="text-[11px] font-bold uppercase tracking-widest flex items-center gap-1 justify-end text-zinc-500">
                                    {{ t('news.next_article') }} <ChevronRight :size="11" :stroke-width="2.2" aria-hidden="true" />
                                </span>
                                <span class="text-[13px] font-semibold line-clamp-2 transition-colors"
                                    :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-500 group-hover:text-blue-600'">{{ next.title }}</span>
                            </Link>
                        </nav>
                    </article>

                    <ArticleComments :article-slug="article.slug" :comments="comments" />
                </div>

                <ArticleSidebar :article="article" :related="related">
                    <ExtensionSlot name="news.show.sidebar" :context="{ articleId: article.id }" />
                </ArticleSidebar>
            </div>

            <ExtensionSlot name="news.show.bottom" :context="{ articleId: article.id }" />
        </div>
    </PublicLayout>
</template>
