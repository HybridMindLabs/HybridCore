<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Newspaper } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import ArticleCard, { type NewsArticleCard } from '@/components/News/ArticleCard.vue';
import NewsPagination from '@/components/News/NewsPagination.vue';

interface Category {
    id: number; name: string; slug: string; color: string;
    icon: string | null; description: string | null;
    meta_title: string | null; meta_description: string | null;
}
interface Pagination { data: NewsArticleCard[]; current_page: number; last_page: number; total: number }

const props = defineProps<{ category: Category; articles: Pagination; canonical: string }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const countLabel = computed(() =>
    props.articles.total === 1
        ? t('news.article_one')
        : t('news.articles_many', { count: props.articles.total }),
);

const metaDescription = computed(
    () => props.category.meta_description || props.category.description || t('news.category_meta', { name: props.category.name }),
);

function pageLink(page: number) {
    return route('news.category', { category: props.category.slug, page });
}
</script>

<template>
    <Head>
        <title>{{ category.meta_title || category.name }}</title>
        <meta name="description" :content="metaDescription" />
        <link rel="canonical" :href="canonical" />
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
                <div class="absolute -top-20 left-0 w-[500px] h-[500px] rounded-full blur-[120px] opacity-[0.07]"
                    :style="{ background: category.color }" />
                <div v-if="dark" class="absolute inset-0 opacity-50"
                    style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
            </div>

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-12 sm:py-16">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    { label: t('news.breadcrumb_news'), href: route('news.index') },
                    { label: category.name },
                ]" />

                <div class="max-w-2xl">
                    <!-- Eyebrow carries the count, like the news index does.
                         The decorative colour block that used to sit opposite
                         only repeated the category name into empty space. -->
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                        :style="{
                            color: category.color,
                            borderColor: category.color + '40',
                            backgroundColor: category.color + '12'
                        }">
                        <span class="w-1.5 h-1.5 rounded-full" :style="{ background: category.color }" />
                        {{ t('news.category_eyebrow') }} · {{ countLabel }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ category.name }}
                    </h1>

                    <p v-if="category.description" class="mt-4 text-[15px] leading-relaxed max-w-lg"
                        :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                        {{ category.description }}
                    </p>

                    <Link :href="route('news.index')"
                        class="inline-flex items-center gap-2 border font-bold text-[13px] px-5 py-2.5 rounded-xl transition mt-7
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        :class="dark
                            ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]'
                            : 'border-zinc-300 text-zinc-700 hover:border-zinc-400 hover:bg-white'">
                        <ArrowLeft :size="13" :stroke-width="2.2" aria-hidden="true" />
                        {{ t('news.all_news') }}
                    </Link>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <div v-if="articles.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <!-- Category hidden: the whole listing is this one category. -->
                <ArticleCard v-for="a in articles.data" :key="a.id" :article="a" :show-category="false" />
            </div>

            <div v-else class="rounded-2xl border p-16 text-center"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                <Newspaper :size="26" :stroke-width="1.5" aria-hidden="true" class="mx-auto mb-3"
                    :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    {{ t('news.category_empty') }}
                </p>
                <p class="text-[12px] mt-1" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('news.category_empty_hint') }}
                </p>
                <Link :href="route('news.index')"
                    class="mt-4 inline-flex items-center gap-1.5 text-[12px] font-semibold transition"
                    :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'">
                    <ArrowLeft :size="12" :stroke-width="2" aria-hidden="true" />
                    {{ t('news.all_news') }}
                </Link>
            </div>

            <NewsPagination
                :current-page="articles.current_page"
                :last-page="articles.last_page"
                :href="pageLink"
            />
        </div>
    </PublicLayout>
</template>
