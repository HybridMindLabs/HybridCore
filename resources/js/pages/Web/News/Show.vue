<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight, Clock, Copy, Eye, MessageSquare, Tag, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import ReportButton from '@/components/UI/ReportButton.vue';

interface Category { id: number; name: string; slug: string; color: string }
interface ArticleCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    category: Category | null; published_at: string;
}
interface Article {
    id: number; title: string; slug: string; excerpt: string | null;
    body: string; format: string; featured_image_url: string | null;
    reading_time: number; views: number; word_count: number;
    category: Category | null;
    author: { id: number; name: string; username: string | null; avatar: string | null } | null;
    tags: { id: number; name: string; slug: string }[];
    published_at: string; published_at_iso: string;
    meta_title: string; meta_description: string | null; og_image_url: string | null;
    canonical: string;
}

interface Comment {
    id: number; body: string; created_at_iso: string; is_mine: boolean; can_delete: boolean;
    user: { username: string | null; name: string; avatar: string | null };
}
interface CommentPage { data: Comment[]; current_page: number; last_page: number; total: number }

const props = defineProps<{
    article: Article;
    related: ArticleCard[];
    prev: { id: number; title: string; slug: string } | null;
    next: { id: number; title: string; slug: string } | null;
    comments: CommentPage;
}>();

// ── Comments ───────────────────────────────────────────────────────────────
const page = usePage<{ auth: { user: { name: string } | null } }>();
const authed = computed(() => !!page.props.auth?.user);

/**
 * Comments arrive a page at a time — the whole thread used to be serialised
 * into the payload on first paint.
 *
 * A partial reload *replaces* the comments prop rather than extending it, so
 * the thread is accumulated here and each fetched page appended to it.
 */
const visibleComments = ref<Comment[]>([...props.comments.data]);
const loadedPage = ref(props.comments.current_page);
const loadingMore = ref(false);

const hasMoreComments = computed(() => loadedPage.value < props.comments.last_page);

// Posting or deleting reloads the page from the top; start the thread over.
watch(
    () => props.comments,
    (next) => {
        if (next.current_page === 1) {
            visibleComments.value = [...next.data];
            loadedPage.value = 1;
        }
    },
);

function loadMoreComments() {
    if (loadingMore.value) return;
    loadingMore.value = true;

    router.get(
        route('news.show', props.article.slug),
        { comments_page: loadedPage.value + 1 },
        {
            only: ['comments'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                visibleComments.value.push(...props.comments.data);
                loadedPage.value = props.comments.current_page;
            },
            onFinish: () => {
                loadingMore.value = false;
            },
        },
    );
}

const commentForm = useForm({ body: '' });

function submitComment() {
    if (commentForm.body.trim().length < 2) return;
    commentForm.post(route('news.comments.store', props.article.slug), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset('body'),
    });
}

function deleteComment(commentId: number) {
    router.delete(route('news.comments.destroy', { article: props.article.slug, comment: commentId }), {
        preserveScroll: true,
    });
}

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');

/**
 * Search engines read this; it is the one page on the site where structured
 * data is worth having, and it costs nothing at runtime.
 */
const jsonLd = computed(() =>
    JSON.stringify({
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
    }),
);

/**
 * Long articles had no way to skim or jump. Headings are pulled out of the
 * rendered body and given stable ids, which powers both the sidebar contents
 * list and any deep link into a section.
 */
const parsedBody = computed(() => {
    if (typeof window === 'undefined') {
        return { html: props.article.body, headings: [] as { id: string; text: string; level: number }[] };
    }

    const doc = new DOMParser().parseFromString(props.article.body, 'text/html');
    const headings: { id: string; text: string; level: number }[] = [];

    doc.querySelectorAll('h2, h3').forEach((node, index) => {
        const text = node.textContent?.trim() ?? '';
        if (!text) return;

        const id = node.id || `section-${index + 1}`;
        node.id = id;
        headings.push({ id, text, level: node.tagName === 'H2' ? 2 : 3 });
    });

    return { html: doc.body.innerHTML, headings };
});

/**
 * With the lead image behind the hero, the text sits on a dark scrim in both
 * themes, so its colours stop following the theme and become fixed light.
 */
const hasHeroImage = computed(() => !!props.article.featured_image_url);

const heroTitle = computed(() => (hasHeroImage.value ? 'text-white' : dark.value ? 'text-zinc-100' : 'text-zinc-900'));
const heroBody = computed(() => (hasHeroImage.value ? 'text-zinc-200' : dark.value ? 'text-zinc-400' : 'text-zinc-600'));
const heroMuted = computed(() => (hasHeroImage.value ? 'text-zinc-300' : dark.value ? 'text-zinc-400' : 'text-zinc-500'));
const heroDivider = computed(() => (hasHeroImage.value ? 'text-white/40' : dark.value ? 'text-zinc-700' : 'text-zinc-300'));
const heroLink = computed(() =>
    hasHeroImage.value
        ? 'text-blue-300 hover:text-blue-200'
        : dark.value
          ? 'text-blue-400 hover:text-blue-300'
          : 'text-blue-600 hover:text-blue-700',
);

const readingProgress = ref(0);
const articleEl = ref<HTMLElement | null>(null);

function updateProgress() {
    const el = articleEl.value;
    if (!el) return;

    const start = el.offsetTop;
    const scrollable = el.offsetHeight - window.innerHeight;

    if (scrollable <= 0) {
        readingProgress.value = window.scrollY > start ? 100 : 0;

        return;
    }

    const passed = window.scrollY - start;
    readingProgress.value = Math.min(100, Math.max(0, Math.round((passed / scrollable) * 100)));
}

onMounted(() => window.addEventListener('scroll', updateProgress, { passive: true }));
onBeforeUnmount(() => window.removeEventListener('scroll', updateProgress));

// Copy link
const copied = ref(false);
function copyLink() {
    navigator.clipboard.writeText(props.article.canonical).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}

// Avatar fallback initials
const initials = computed(() => {
    const name = props.article.author?.name ?? '?';
    return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
});
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
        <component :is="'script'" type="application/ld+json">{{ jsonLd }}</component>
    </Head>

    <PublicLayout>

        <!-- Position in a long read, which the page gave no sense of. -->
        <div class="fixed top-0 left-0 right-0 h-0.5 z-50 pointer-events-none">
            <div class="h-full bg-blue-500 transition-[width] duration-150 ease-out"
                role="progressbar"
                :aria-label="t('news.reading_progress')"
                :aria-valuenow="readingProgress"
                aria-valuemin="0"
                aria-valuemax="100"
                :style="{ width: readingProgress + '%' }" />
        </div>

        <!-- ══════════════════════════════════════════════ HERO -->
        <div class="relative overflow-hidden border-b"
            :class="hasHeroImage
                ? 'border-zinc-800/60 bg-[#09090b]'
                : dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'">

            <!-- The lead image, as the backdrop. -->
            <template v-if="hasHeroImage">
                <img :src="article.featured_image_url!" alt=""
                    class="absolute inset-0 w-full h-full object-cover" />
                <!--
                    Text sits on an arbitrary photo, so the scrim has to hold
                    contrast against the worst case — a white image. At 85%
                    black the lightest possible backdrop is #262626, which keeps
                    white text above 15:1; the lighter top only carries the
                    breadcrumb, and still clears 5.7:1.
                -->
                <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/85 to-black/65" />
                <div class="absolute inset-0 opacity-40"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.05) 1px,transparent 1px);background-size:28px 28px" />
            </template>

            <!-- Without an image, the same glow treatment the other pages use. -->
            <div v-else class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="article.category" class="absolute -top-20 left-0 w-[400px] h-[400px] rounded-full blur-[120px] opacity-[0.06]"
                    :style="{ background: article.category.color }" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <!-- Same container as the body below. The hero used to sit in a
                 1600px shell over a 1100px article, so the headline started
                 well left of its own text and left a band of dead space. -->
            <div class="relative z-10 max-w-[1180px] mx-auto px-4 sm:px-6"
                :class="hasHeroImage ? 'pt-14 pb-16 sm:pt-20 sm:pb-20' : 'pt-10 pb-12'">

                <nav :aria-label="t('news.breadcrumb_news')" class="flex items-center gap-2 text-[12px] mb-6 min-w-0"
                    :class="heroMuted">
                    <Link :href="route('news.index')" class="transition shrink-0"
                        :class="hasHeroImage ? 'hover:text-white' : dark ? 'hover:text-zinc-200' : 'hover:text-zinc-800'">{{ t('news.breadcrumb_news') }}</Link>
                    <ChevronRight :size="11" :stroke-width="2" aria-hidden="true" class="shrink-0" />
                    <Link v-if="article.category" :href="route('news.category', article.category.slug)"
                        class="font-semibold transition shrink-0" :style="{ color: article.category.color }">
                        {{ article.category.name }}
                    </Link>
                    <ChevronRight v-if="article.category" :size="11" :stroke-width="2" aria-hidden="true" class="shrink-0" />
                    <span class="truncate">{{ article.title }}</span>
                </nav>

                <!-- One column, measured. A headline is easier to read short. -->
                <div class="max-w-[46rem]">
                    <!-- Over a photo the category's own colour is not reliably
                         readable, so the badge keeps the hue only as a dot and
                         puts the label on white. -->
                    <Link v-if="article.category" :href="route('news.category', article.category.slug)"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest transition mb-5"
                        :class="hasHeroImage ? 'border-white/25 bg-white/10 text-white backdrop-blur-sm hover:bg-white/20' : ''"
                        :style="hasHeroImage ? {} : {
                            color: article.category.color,
                            borderColor: article.category.color + '40',
                            backgroundColor: article.category.color + '12'
                        }">
                        <span class="w-1.5 h-1.5 rounded-full" :style="{ background: article.category.color }" />
                        {{ article.category.name }}
                    </Link>

                    <h1 class="text-3xl sm:text-[2.75rem] font-black tracking-tight leading-[1.1]"
                        :class="heroTitle">
                        {{ article.title }}
                    </h1>

                    <p v-if="article.excerpt" class="mt-4 text-[16px] leading-relaxed"
                        :class="heroBody">
                        {{ article.excerpt }}
                    </p>

                    <!-- One quiet meta line. These numbers were three large
                         stat pills plus a duplicate strip in the sidebar. -->
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-6 text-[13px]"
                        :class="heroMuted">
                        <div v-if="article.author" class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full overflow-hidden border shrink-0 flex items-center justify-center text-[10px] font-bold"
                                :class="hasHeroImage ? 'border-white/25 bg-white/10 text-zinc-200' : dark ? 'border-zinc-700 bg-zinc-800 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                <img v-if="article.author.avatar" :src="article.author.avatar"
                                    :alt="t('news.author_avatar_alt', { name: article.author.name })"
                                    class="w-full h-full object-cover" />
                                <span v-else aria-hidden="true">{{ initials }}</span>
                            </span>
                            <span class="font-semibold" :class="hasHeroImage ? 'text-white' : dark ? 'text-zinc-200' : 'text-zinc-800'">{{ article.author.name }}</span>
                        </div>
                        <span aria-hidden="true" :class="heroDivider">·</span>
                        <time :datetime="article.published_at_iso">{{ formatDate(article.published_at_iso, { dateStyle: 'long' }) }}</time>
                        <span aria-hidden="true" :class="heroDivider">·</span>
                        <span class="flex items-center gap-1.5">
                            <Clock :size="12" :stroke-width="1.8" aria-hidden="true" />
                            {{ t('news.stat_minutes', { m: article.reading_time }) }}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Eye :size="12" :stroke-width="1.8" aria-hidden="true" />
                            {{ article.views.toLocaleString() }}
                        </span>
                        <a :href="'#comments'" class="flex items-center gap-1.5 font-semibold transition"
                            :class="heroLink">
                            <MessageSquare :size="12" :stroke-width="2" aria-hidden="true" />
                            {{ comments.total }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- ══════════════════════════════════════════ END HERO -->

        <!-- ══════════════════════ CONTENT + SIDEBAR -->
        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-10">

            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_260px] gap-10 items-start">

                <!-- Article and its comments are one column; the sidebar is the
                     other. Relying on auto-placement here put the comments in
                     the sidebar track. -->
                <div class="min-w-0">

                <!-- ── Article body ── -->
                <article ref="articleEl">
                    <!-- Capped measure: a full-width column ran to well over a
                         hundred characters a line, which is where the reading
                         got hard. ~70 is the comfortable range. -->
                    <div class="prose max-w-[68ch] prose-headings:scroll-mt-24"
                        :class="dark
                            ? 'prose-invert prose-zinc prose-headings:text-zinc-100 prose-headings:font-black prose-p:text-zinc-300 prose-a:text-blue-400 prose-strong:text-zinc-100 prose-code:text-blue-300 prose-code:bg-zinc-900/60 prose-code:text-[13px] prose-pre:bg-[#0d0d0f] prose-pre:border prose-pre:border-zinc-800/70 prose-blockquote:border-l-blue-500/50 prose-blockquote:text-zinc-400 prose-blockquote:bg-zinc-900/30 prose-hr:border-zinc-800/70 prose-img:rounded-xl prose-img:border prose-img:border-zinc-800/70'
                            : 'prose-zinc prose-headings:font-black prose-a:text-blue-600 prose-code:bg-zinc-100 prose-pre:bg-zinc-50 prose-pre:border prose-pre:border-zinc-200 prose-blockquote:border-l-blue-400 prose-img:rounded-xl prose-img:border prose-img:border-zinc-200'"
                        v-html="parsedBody.html" />

                    <!-- Tags -->
                    <div v-if="article.tags.length" class="flex flex-wrap gap-2 mt-10 pt-7 border-t"
                        :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                        <Link v-for="t in article.tags" :key="t.slug"
                            :href="route('news.tag', t.slug)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[12px] font-semibold transition"
                            :class="dark
                                ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-100 hover:border-zinc-600'
                                : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                            <Tag :size="10" :stroke-width="2" />#{{ t.name }}
                        </Link>
                    </div>

                    <!-- Prev / Next -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-8 pt-7 border-t"
                        :class="dark ? 'border-zinc-800/70' : 'border-zinc-200'">
                        <Link v-if="prev" :href="route('news.show', prev.slug)"
                            class="flex flex-col gap-1.5 p-4 rounded-xl border transition group"
                            :class="dark
                                ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
                                : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm'">
                            <span class="text-[11px] font-bold uppercase tracking-widest flex items-center gap-1"
                                :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                                <ChevronLeft :size="11" :stroke-width="2.2" aria-hidden="true" /> {{ t('news.prev_article') }}
                            </span>
                            <p class="text-[13px] font-semibold line-clamp-2 transition-colors"
                                :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-700 group-hover:text-blue-600'">
                                {{ prev.title }}
                            </p>
                        </Link>
                        <Link v-if="next" :href="route('news.show', next.slug)"
                            class="flex flex-col gap-1.5 p-4 rounded-xl border transition group text-right"
                            :class="[
                                dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60' : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm',
                                !prev ? 'sm:col-start-2' : ''
                            ]">
                            <span class="text-[11px] font-bold uppercase tracking-widest flex items-center gap-1 justify-end"
                                :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                                {{ t('news.next_article') }} <ChevronRight :size="11" :stroke-width="2.2" aria-hidden="true" />
                            </span>
                            <p class="text-[13px] font-semibold line-clamp-2 transition-colors"
                                :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-700 group-hover:text-blue-600'">
                                {{ next.title }}
                            </p>
                        </Link>
                    </div>

                </article>

                <!-- Comments sit outside <article>: they are responses to it,
                     not part of the piece itself. -->
                <section id="comments" class="scroll-mt-20">
                    <div class="mt-8 rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-sm'">
                        <div class="px-5 py-3.5 border-b flex items-center gap-2"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <MessageSquare :size="13" :stroke-width="1.8" :class="dark ? 'text-zinc-600' : 'text-zinc-400'" />
                            <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ t('news.comments') }}</p>
                            <span class="text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">({{ comments.total }})</span>
                        </div>

                        <!-- Write comment -->
                        <div v-if="authed" class="px-5 py-4 border-b" :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                            <form class="flex flex-col gap-2.5" @submit.prevent="submitComment">
                                <textarea
                                    v-model="commentForm.body"
                                    rows="3"
                                    maxlength="1000"
                                    :placeholder="t('news.comment_placeholder')"
                                    class="w-full rounded-xl border px-4 py-2.5 text-[13px] resize-none transition focus:outline-none"
                                    :class="dark
                                        ? 'border-zinc-800 bg-zinc-900 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                                        : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400'"
                                />
                                <p v-if="commentForm.errors.body" class="text-[12px] text-red-400">{{ commentForm.errors.body }}</p>
                                <div>
                                    <button
                                        type="submit"
                                        :disabled="commentForm.processing || commentForm.body.trim().length < 2"
                                        class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-[12px] font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ commentForm.processing ? t('news.posting') : t('news.post_comment') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div v-else class="px-5 py-4 border-b" :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                            <p class="text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                <Link :href="route('login')" class="font-semibold"
                                    :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'">{{ t('news.login') }}</Link>
                                {{ t('news.comment_login_suffix') }}
                            </p>
                        </div>

                        <!-- Comment list -->
                        <div v-if="visibleComments.length" class="divide-y" :class="dark ? 'divide-zinc-800/50' : 'divide-zinc-100'">
                            <div v-for="comment in visibleComments" :key="comment.id" class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <component
                                        :is="comment.user.username ? Link : 'div'"
                                        :href="comment.user.username ? route('profile.show', comment.user.username) : undefined"
                                        class="w-9 h-9 rounded-xl overflow-hidden shrink-0"
                                    >
                                        <img v-if="comment.user.avatar" :src="comment.user.avatar" class="w-full h-full object-cover"
                                            :alt="t('news.author_avatar_alt', { name: comment.user.name })" />
                                        <div v-else aria-hidden="true"
                                            class="w-full h-full flex items-center justify-center text-[13px] font-black text-white uppercase"
                                            :style="{ backgroundColor: avatarBg(comment.user.name) }">{{ comment.user.name[0] }}</div>
                                    </component>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <component
                                                :is="comment.user.username ? Link : 'span'"
                                                :href="comment.user.username ? route('profile.show', comment.user.username) : undefined"
                                                class="text-[13px] font-bold truncate"
                                                :class="[dark ? 'text-zinc-100' : 'text-zinc-800', comment.user.username ? (dark ? 'hover:text-blue-400' : 'hover:text-blue-600') : '']"
                                            >{{ comment.user.name }}</component>
                                            <time :datetime="comment.created_at_iso" class="text-[11px]"
                                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                                {{ formatDate(comment.created_at_iso, { dateStyle: 'medium', timeStyle: 'short' }) }}
                                            </time>
                                            <ReportButton v-if="authed && !comment.is_mine" type="comment" :id="comment.id" class="ml-auto" />
                                            <button
                                                v-if="comment.can_delete"
                                                type="button"
                                                class="p-1 rounded transition"
                                                :class="[dark ? 'text-zinc-600 hover:text-red-400' : 'text-zinc-300 hover:text-red-500', (authed && !comment.is_mine) ? '' : 'ml-auto']"
                                                :title="t('news.delete_comment')"
                                                @click="deleteComment(comment.id)"
                                            >
                                                <Trash2 :size="12" :stroke-width="2" />
                                            </button>
                                        </div>
                                        <p class="text-[13px] leading-relaxed mt-1 whitespace-pre-line"
                                            :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ comment.body }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center px-5 py-10 text-center">
                            <MessageSquare :size="22" :stroke-width="1.5" aria-hidden="true" class="mb-2" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                            <p class="text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('news.no_comments') }}</p>
                        </div>

                        <div v-if="hasMoreComments" class="px-5 py-4 border-t flex flex-col items-center gap-1.5"
                            :class="dark ? 'border-zinc-800/50' : 'border-zinc-100'">
                            <button type="button" :disabled="loadingMore"
                                class="text-[12px] font-semibold transition disabled:opacity-60 rounded
                                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
                                @click="loadMoreComments">
                                {{ t('news.load_more_comments') }}
                            </button>
                            <p class="text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                {{ t('news.comments_shown', { shown: visibleComments.length, total: comments.total }) }}
                            </p>
                        </div>
                    </div>
                </section>

                </div>

                <!-- ── Sidebar ── -->
                <aside class="flex flex-col gap-4 xl:sticky xl:top-20">

                    <ExtensionSlot name="news.show.sidebar" :context="{ articleId: article.id }" />

                    <!-- Contents: the only way to skim a long piece. Built from
                         the headings in the body, so it costs no extra data. -->
                    <nav v-if="parsedBody.headings.length > 1" :aria-label="t('news.toc_title')"
                        class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ t('news.toc_title') }}</h3>
                        </div>
                        <ol class="p-3 flex flex-col gap-0.5">
                            <li v-for="heading in parsedBody.headings" :key="heading.id">
                                <a :href="`#${heading.id}`"
                                    class="block py-1.5 px-2 rounded-lg text-[12px] leading-snug transition"
                                    :class="[
                                        heading.level === 3 ? 'pl-5' : '',
                                        dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-white/[0.04]' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50',
                                    ]">
                                    {{ heading.text }}
                                </a>
                            </li>
                        </ol>
                    </nav>

                    <!-- Author card -->
                    <div v-if="article.author" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ t('news.sidebar_author') }}</h3>
                        </div>
                        <div class="p-4 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full overflow-hidden border shrink-0 flex items-center justify-center font-bold text-[13px]"
                                :class="dark ? 'border-zinc-700 bg-zinc-800 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                                <img v-if="article.author.avatar" :src="article.author.avatar" class="w-full h-full object-cover" />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <div>
                                <p class="text-[14px] font-bold"
                                    :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ article.author.name }}</p>
                                <p v-if="article.author.username" class="text-[12px]"
                                    :class="dark ? 'text-zinc-600' : 'text-zinc-400'">@{{ article.author.username }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Share -->
                    <div class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ t('news.sidebar_share') }}</h3>
                        </div>
                        <div class="p-3">
                            <button type="button" @click="copyLink"
                                class="w-full flex items-center justify-center gap-2 py-2 rounded-xl border text-[12px] font-semibold transition"
                                :class="copied
                                    ? dark ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400' : 'border-emerald-400/40 bg-emerald-50 text-emerald-700'
                                    : dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                                <component :is="copied ? Check : Copy" :size="12" :stroke-width="2" />
                                {{ copied ? t('news.copied') : t('news.copy_link') }}
                            </button>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="article.tags.length" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ t('news.sidebar_tags') }}</h3>
                        </div>
                        <div class="p-3 flex flex-wrap gap-1.5">
                            <Link v-for="t in article.tags" :key="t.slug"
                                :href="route('news.tag', t.slug)"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-[11px] font-semibold transition"
                                :class="dark
                                    ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'
                                    : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                                <Tag :size="9" :stroke-width="2" />#{{ t.name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Related articles -->
                    <div v-if="related.length" class="rounded-2xl border overflow-hidden"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                        <div class="border-b px-4 py-3"
                            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
                            <h3 class="text-[12px] font-bold uppercase tracking-widest"
                                :class="dark ? 'text-zinc-400' : 'text-zinc-500'">{{ t('news.sidebar_related') }}</h3>
                        </div>
                        <div class="p-3 flex flex-col gap-1">
                            <Link v-for="a in related" :key="a.id"
                                :href="route('news.show', a.slug)"
                                class="flex gap-3 items-center p-2 rounded-xl transition group"
                                :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50'">
                                <div class="w-12 h-12 rounded-lg overflow-hidden border shrink-0"
                                    :class="dark ? 'border-zinc-800/70 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'">
                                    <img v-if="a.featured_image_url" :src="a.featured_image_url" class="w-full h-full object-cover" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[12px] font-semibold line-clamp-2 transition-colors"
                                        :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-700 group-hover:text-blue-600'">
                                        {{ a.title }}
                                    </p>
                                    <p class="text-[11px] mt-0.5 flex items-center gap-1"
                                        :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                                        <Clock :size="9" />{{ a.reading_time }}m
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </div>

                </aside>
            </div>

            <ExtensionSlot name="news.show.bottom" :context="{ articleId: article.id }" />
        </div>

    </PublicLayout>
</template>