<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Eye, Newspaper } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

export interface NewsArticleCard {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    featured_image_url: string | null;
    reading_time: number;
    views: number;
    published_at_iso: string | null;
    category: { name: string; slug: string; color: string } | null;
    author?: { name: string } | null;
}

const props = defineProps<{
    article: NewsArticleCard;
    /** Hidden where the whole listing is already one category. */
    showCategory?: boolean;
}>();

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');

const withCategory = computed(() => props.showCategory !== false && !!props.article.category);
</script>

<template>
    <Link
        :href="route('news.show', article.slug)"
        class="group rounded-2xl border overflow-hidden flex flex-col transition-all duration-200
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
        :class="dark
            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
            : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-md shadow-sm'"
    >
        <div class="relative overflow-hidden h-40 shrink-0" :class="dark ? 'bg-zinc-900/60' : 'bg-zinc-100'">
            <img
                v-if="article.featured_image_url"
                :src="article.featured_image_url"
                :alt="t('news.article_image_alt', { title: article.title })"
                loading="lazy"
                decoding="async"
                class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                :class="dark ? 'opacity-80 group-hover:opacity-100' : ''"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
                <Newspaper :size="24" :stroke-width="1.2" aria-hidden="true" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
            </div>

            <div v-if="withCategory" class="absolute top-2 left-2">
                <span
                    class="text-[10px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm"
                    :style="{ backgroundColor: article.category!.color + '25', color: article.category!.color }"
                >{{ article.category!.name }}</span>
            </div>
        </div>

        <div class="p-4 flex flex-col gap-2 flex-1">
            <h3
                class="text-[14px] font-bold line-clamp-2 transition"
                :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'"
            >{{ article.title }}</h3>

            <p v-if="article.excerpt" class="text-[12px] line-clamp-2 flex-1" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ article.excerpt }}
            </p>

            <!-- A news card without a date makes an old post look current. -->
            <div class="flex flex-col gap-1.5 mt-auto text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                <div class="flex items-center gap-3">
                    <time v-if="article.published_at_iso" :datetime="article.published_at_iso">
                        {{ formatDate(article.published_at_iso) }}
                    </time>
                    <span class="flex items-center gap-1">
                        <Clock :size="10" aria-hidden="true" />{{ t('news.read_time_short', { m: article.reading_time }) }}
                    </span>
                    <span class="flex items-center gap-1">
                        <Eye :size="10" aria-hidden="true" />{{ article.views }}
                    </span>
                </div>
                <span v-if="article.author" class="truncate">{{ article.author.name }}</span>
            </div>
        </div>
    </Link>
</template>
