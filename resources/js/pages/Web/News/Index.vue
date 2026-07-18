<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Search, Clock, Eye, Rss, Newspaper, Star, LayoutGrid, X, ChevronLeft, ChevronRight } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface Category { id: number; name: string; slug: string; color: string; icon: string; articles_count: number }
interface ArticleCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    category: Category | null; author: { name: string } | null;
    published_at: string; is_featured: boolean; is_pinned: boolean;
    tags: { name: string; slug: string }[];
}
interface Pagination { data: ArticleCard[]; current_page: number; last_page: number; total: number }

const props = defineProps<{
    articles: Pagination;
    categories: Category[];
    featuredArticles: ArticleCard[];
    currentCategory: string | null;
    currentTag: string | null;
    search: string | null;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const q = ref(props.search ?? '');

function doSearch() {
    router.get(route('news.index'), { search: q.value || undefined }, { preserveState: true });
}

function clearSearch() {
    q.value = '';
    router.get(route('news.index'));
}

const countLabel = computed(() =>
    props.articles.total === 1
        ? t('news.article_one')
        : t('news.articles_many', { count: props.articles.total }),
);

// Only show the featured block on the unfiltered listing — under a filter the
// reader asked for something specific and shouldn't be handed editor picks.
const showFeatured = computed(() =>
    !props.currentCategory && !props.currentTag && !props.search && props.featuredArticles.length > 0,
);

/**
 * A short window of page links around the current page. Rendering every page
 * turns a large archive into hundreds of links.
 */
const pageWindow = computed(() => {
    const { current_page: current, last_page: last } = props.articles;
    const span = 2;
    const from = Math.max(1, current - span);
    const to = Math.min(last, current + span);

    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
});

function pageLink(page: number) {
    return route('news.index', {
        page,
        search: props.search || undefined,
    });
}
</script>

<template>
    <Head>
        <title>{{ t('news.page_title') }}</title>
        <link rel="alternate" type="application/rss+xml" :title="t('news.page_title')" :href="route('news.feed')" />
    </Head>

    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'">

            <div class="absolute inset-0 pointer-events-none overflow-hidden">
                <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]"
                    :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
                <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-12 sm:py-16">
                <Breadcrumb :items="[{ label: t('navigation.nav_home'), href: route('home') }, { label: t('news.page_title') }]" />

                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8">
                    <div class="max-w-2xl">
                        <div
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                            :class="dark ? 'border-blue-500/25 bg-blue-500/8 text-blue-400' : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                        >
                            <Newspaper :size="11" :stroke-width="2.2" />
                            {{ countLabel }}
                        </div>

                        <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            <template v-if="currentCategory">{{ currentCategory }}</template>
                            <template v-else-if="currentTag">{{ t('news.tagged_heading', { tag: currentTag }) }}</template>
                            <template v-else-if="search">{{ t('news.search_heading', { query: search }) }}</template>
                            <template v-else>
                                {{ t('news.heading') }}
                                <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">{{ t('news.heading_accent') }}</span>
                            </template>
                        </h1>

                        <p class="mt-4 text-[15px] leading-relaxed max-w-lg"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ t('news.subtitle') }}
                        </p>

                        <!-- Search -->
                        <form class="relative w-full max-w-sm mt-8" @submit.prevent="doSearch">
                            <Search :size="14" :stroke-width="1.8" class="absolute left-3.5 top-1/2 -translate-y-1/2"
                                :class="dark ? 'text-zinc-600' : 'text-zinc-400'" />
                            <input v-model="q" type="text" :placeholder="t('news.search')"
                                class="w-full rounded-xl border pl-10 pr-10 py-3 text-[14px] font-medium transition focus:outline-none focus:ring-2"
                                :class="dark
                                    ? 'border-zinc-800 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50 focus:ring-blue-500/10'
                                    : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400/60 focus:ring-blue-500/10'"
                                @keydown.enter="doSearch" />
                            <button v-if="search" type="button" :title="t('news.clear_search')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 transition"
                                :class="dark ? 'text-zinc-600 hover:text-zinc-300' : 'text-zinc-400 hover:text-zinc-700'"
                                @click="clearSearch">
                                <X :size="14" :stroke-width="2" />
                            </button>
                        </form>
                    </div>

                    <a :href="route('news.feed')"
                        class="inline-flex items-center gap-1.5 text-[12px] font-semibold transition self-start lg:self-end"
                        :class="dark ? 'text-zinc-600 hover:text-amber-400' : 'text-zinc-400 hover:text-amber-500'">
                        <Rss :size="13" :stroke-width="2" /> {{ t('news.rss_feed') }}
                    </a>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">

            <!-- Featured — labelled, so it's clear these are picks and not the feed -->
            <section v-if="showFeatured" class="mb-10">
                <div class="flex items-baseline gap-3 mb-4">
                    <h2 class="text-[15px] font-bold flex items-center gap-2"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        <Star :size="14" :stroke-width="2" class="text-amber-400" />
                        {{ t('news.featured') }}
                    </h2>
                    <p class="text-[12px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('news.featured_hint') }}</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-4">
                    <Link v-if="featuredArticles[0]" :href="route('news.show', featuredArticles[0].slug)"
                        class="relative overflow-hidden rounded-2xl border group min-h-[300px] flex flex-col justify-end transition-colors"
                        :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700' : 'border-zinc-200 bg-zinc-900 hover:border-zinc-400'">
                        <img v-if="featuredArticles[0].featured_image_url" :src="featuredArticles[0].featured_image_url"
                            :alt="featuredArticles[0].title"
                            class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-50 transition duration-500" />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />
                        <div class="relative p-6">
                            <div v-if="featuredArticles[0].category" class="mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                                    :style="{ backgroundColor: featuredArticles[0].category.color+'20', color: featuredArticles[0].category.color }">
                                    {{ featuredArticles[0].category.name }}
                                </span>
                            </div>
                            <h3 class="text-[22px] font-black text-white leading-snug mb-2 group-hover:text-blue-100 transition">{{ featuredArticles[0].title }}</h3>
                            <p v-if="featuredArticles[0].excerpt" class="text-[13px] text-zinc-300 line-clamp-2">{{ featuredArticles[0].excerpt }}</p>
                            <div class="flex items-center gap-3 mt-3 text-[12px] text-zinc-400">
                                <span class="flex items-center gap-1"><Clock :size="11" />{{ t('news.read_time_short', { m: featuredArticles[0].reading_time }) }}</span>
                                <span class="flex items-center gap-1"><Eye :size="11" />{{ featuredArticles[0].views }}</span>
                            </div>
                        </div>
                    </Link>

                    <div v-if="featuredArticles.length > 1" class="flex flex-col gap-3">
                        <Link v-for="a in featuredArticles.slice(1, 4)" :key="a.id" :href="route('news.show', a.slug)"
                            class="flex gap-3 items-start p-3 rounded-xl border transition group"
                            :class="dark
                                ? 'border-zinc-800/70 bg-[#111113] hover:bg-[#1a1a1e]'
                                : 'border-zinc-200 bg-white hover:border-zinc-300 shadow-sm'">
                            <img v-if="a.featured_image_url" :src="a.featured_image_url" :alt="a.title"
                                class="w-16 h-16 rounded-lg object-cover shrink-0 border"
                                :class="dark ? 'border-zinc-800/70' : 'border-zinc-100'" />
                            <div class="min-w-0">
                                <div v-if="a.category" class="mb-1">
                                    <span class="text-[10px] font-bold" :style="{ color: a.category.color }">{{ a.category.name }}</span>
                                </div>
                                <p class="text-[13px] font-semibold line-clamp-2 transition"
                                    :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">{{ a.title }}</p>
                                <p class="text-[11px] mt-0.5 flex items-center gap-1"
                                    :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                    <Clock :size="9" />{{ t('news.read_time_short', { m: a.reading_time }) }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- Categories — labelled so the pills read as a filter, not decoration -->
            <section v-if="categories.length" class="mb-6">
                <h2 class="text-[12px] font-semibold uppercase tracking-wider mb-2.5"
                    :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('news.browse_categories') }}</h2>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('news.index')"
                        class="px-3 py-1.5 rounded-full border text-[12px] font-semibold transition"
                        :class="!currentCategory
                            ? (dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-300 bg-blue-50 text-blue-600')
                            : (dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-200 bg-white text-zinc-500 hover:text-zinc-800 hover:border-zinc-300')">
                        {{ t('news.all') }}
                    </Link>
                    <Link v-for="c in categories" :key="c.id"
                        :href="route('news.category', c.slug)"
                        class="px-3 py-1.5 rounded-full border text-[12px] font-semibold transition"
                        :class="currentCategory === c.slug
                            ? ''
                            : (dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-200 bg-white text-zinc-500 hover:text-zinc-800 hover:border-zinc-300')"
                        :style="currentCategory === c.slug ? { color: c.color, borderColor: c.color+'50', backgroundColor: c.color+'15' } : {}">
                        {{ c.name }} <span class="opacity-50 text-[10px]">{{ c.articles_count }}</span>
                    </Link>
                </div>
            </section>

            <!-- The feed itself, labelled so it's distinct from Featured above -->
            <section>
                <h2 v-if="showFeatured" class="text-[15px] font-bold flex items-center gap-2 mb-4"
                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    <LayoutGrid :size="14" :stroke-width="2" :class="dark ? 'text-zinc-500' : 'text-zinc-400'" />
                    {{ t('news.all_articles_heading') }}
                </h2>

                <div v-if="articles.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <Link v-for="a in articles.data" :key="a.id" :href="route('news.show', a.slug)"
                        class="group rounded-2xl border overflow-hidden flex flex-col transition-all duration-200"
                        :class="dark
                            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
                            : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-md shadow-sm'">
                        <div class="relative overflow-hidden h-40 shrink-0" :class="dark ? 'bg-zinc-900/60' : 'bg-zinc-100'">
                            <img v-if="a.featured_image_url" :src="a.featured_image_url" :alt="a.title"
                                loading="lazy" decoding="async"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                                :class="dark ? 'opacity-80 group-hover:opacity-100' : ''" />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <Newspaper :size="24" :stroke-width="1.2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                            </div>
                            <div v-if="a.category" class="absolute top-2 left-2">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm"
                                    :style="{ backgroundColor: a.category.color+'25', color: a.category.color }">{{ a.category.name }}</span>
                            </div>
                        </div>
                        <div class="p-4 flex flex-col gap-2 flex-1">
                            <h3 class="text-[14px] font-bold line-clamp-2 transition"
                                :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">{{ a.title }}</h3>
                            <p v-if="a.excerpt" class="text-[12px] line-clamp-2 flex-1" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ a.excerpt }}</p>
                            <div class="flex items-center gap-3 mt-auto text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                                <span class="flex items-center gap-1"><Clock :size="10" />{{ t('news.read_time_short', { m: a.reading_time }) }}</span>
                                <span class="flex items-center gap-1"><Eye :size="10" />{{ a.views }}</span>
                                <span v-if="a.author" class="ml-auto truncate">{{ a.author.name }}</span>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Empty state that offers a way out instead of a dead end -->
                <div v-else class="rounded-2xl border p-16 text-center"
                    :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                    <Newspaper :size="26" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                    <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ t('news.no_articles') }}</p>
                    <p class="text-[12px] mt-1" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('news.no_articles_hint') }}</p>
                    <button v-if="search" type="button"
                        class="mt-4 inline-flex items-center gap-1.5 text-[12px] font-semibold transition"
                        :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
                        @click="clearSearch">
                        <X :size="12" :stroke-width="2" /> {{ t('news.clear_search') }}
                    </button>
                </div>
            </section>

            <!-- Windowed pagination with prev/next and a position readout -->
            <nav v-if="articles.last_page > 1" class="flex flex-col items-center gap-2 mt-8">
                <div class="flex items-center gap-1">
                    <Link v-if="articles.current_page > 1" :href="pageLink(articles.current_page - 1)"
                        class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition"
                        :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                        <ChevronLeft :size="13" :stroke-width="2" /> {{ t('news.prev') }}
                    </Link>

                    <Link v-if="pageWindow[0] > 1" :href="pageLink(1)"
                        class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition"
                        :class="dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800'">1</Link>
                    <span v-if="pageWindow[0] > 2" class="px-1" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">…</span>

                    <Link v-for="p in pageWindow" :key="p" :href="pageLink(p)"
                        class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition"
                        :class="p === articles.current_page
                            ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                            : dark
                                ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'
                                : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                        {{ p }}
                    </Link>

                    <span v-if="pageWindow[pageWindow.length - 1] < articles.last_page - 1" class="px-1" :class="dark ? 'text-zinc-700' : 'text-zinc-400'">…</span>
                    <Link v-if="pageWindow[pageWindow.length - 1] < articles.last_page" :href="pageLink(articles.last_page)"
                        class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition"
                        :class="dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800'">
                        {{ articles.last_page }}
                    </Link>

                    <Link v-if="articles.current_page < articles.last_page" :href="pageLink(articles.current_page + 1)"
                        class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition"
                        :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                        {{ t('news.next') }} <ChevronRight :size="13" :stroke-width="2" />
                    </Link>
                </div>

                <p class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                    {{ t('news.showing', { current: articles.current_page, last: articles.last_page }) }}
                </p>
            </nav>
        </div>
    </PublicLayout>
</template>
