<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Clock, Eye, MessageSquare } from '@lucide/vue';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { initials, type Article } from './articleTypes';

const props = defineProps<{ article: Article; commentCount: number }>();

const { theme } = useTheme();
const { t, formatDate } = useLocale();
const dark = computed(() => theme.value === 'dark');

/**
 * With the lead image behind the hero, the text sits on a dark scrim in both
 * themes, so its colours stop following the theme and become fixed light.
 */
const hasImage = computed(() => !!props.article.featured_image_url);

const title = computed(() => (hasImage.value ? 'text-white' : dark.value ? 'text-zinc-100' : 'text-zinc-900'));
const body = computed(() => (hasImage.value ? 'text-zinc-200' : dark.value ? 'text-zinc-400' : 'text-zinc-500'));
const muted = computed(() => (hasImage.value ? 'text-zinc-300' : dark.value ? 'text-zinc-400' : 'text-zinc-500'));
const divider = computed(() => (hasImage.value ? 'text-white/40' : dark.value ? 'text-zinc-500' : 'text-zinc-300'));
const link = computed(() => hasImage.value
    ? 'text-blue-300 hover:text-blue-200'
    : dark.value ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700');
</script>

<template>
    <div class="relative overflow-hidden border-b"
        :class="hasImage || dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'">

        <template v-if="hasImage">
            <img :src="article.featured_image_url!" alt="" class="absolute inset-0 w-full h-full object-cover" fetchpriority="high" />
            <!-- Text sits on an arbitrary photo, so the scrim holds contrast against
                 the worst case, a white image: white text stays above 15:1. -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/85 to-black/65" aria-hidden="true" />
            <div class="absolute inset-0 opacity-40" aria-hidden="true"
                style="background-image:radial-gradient(circle,rgba(255,255,255,0.05) 1px,transparent 1px);background-size:28px 28px" />
        </template>

        <div v-else class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
            <div class="absolute -top-40 left-1/3 w-[600px] h-[500px] rounded-full blur-[130px]" :class="dark ? 'bg-blue-500/6' : 'bg-blue-400/8'" />
            <div class="absolute -top-20 right-1/4 w-[350px] h-[350px] rounded-full blur-[100px]" :class="dark ? 'bg-violet-500/5' : 'bg-violet-400/5'" />
            <div v-if="article.category" class="absolute -top-20 left-0 w-[400px] h-[400px] rounded-full blur-[120px] opacity-[0.06]"
                :style="{ background: article.category.color }" />
            <div v-if="dark" class="absolute inset-0 opacity-50"
                style="background-image:radial-gradient(circle,rgba(255,255,255,0.035) 1px,transparent 1px);background-size:28px 28px" />
        </div>

        <!-- Same container as the body below, so the headline lines up with its text. -->
        <div class="relative z-10 max-w-[1180px] mx-auto px-4 sm:px-6" :class="hasImage ? 'pt-14 pb-16 sm:pt-20 sm:pb-20' : 'pt-10 pb-12'">
            <nav :aria-label="t('news.breadcrumb_news')" class="flex items-center gap-2 text-[12px] mb-6 min-w-0" :class="muted">
                <Link :href="route('news.index')" class="transition shrink-0"
                    :class="hasImage ? 'hover:text-white' : dark ? 'hover:text-zinc-200' : 'hover:text-zinc-800'">{{ t('news.breadcrumb_news') }}</Link>
                <ChevronRight :size="11" :stroke-width="2" aria-hidden="true" class="shrink-0" />
                <template v-if="article.category">
                    <Link :href="route('news.category', article.category.slug)" class="font-semibold transition shrink-0" :style="{ color: article.category.color }">
                        {{ article.category.name }}
                    </Link>
                    <ChevronRight :size="11" :stroke-width="2" aria-hidden="true" class="shrink-0" />
                </template>
                <span class="truncate" aria-current="page">{{ article.title }}</span>
            </nav>

            <!-- One column, measured. A headline is easier to read short. -->
            <div class="max-w-[46rem]">
                <!-- Over a photo the category's own colour is not reliably readable,
                     so the badge keeps the hue only as a dot and puts the label on white. -->
                <Link v-if="article.category" :href="route('news.category', article.category.slug)"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest transition mb-5"
                    :class="hasImage ? 'border-white/25 bg-white/10 text-white backdrop-blur-sm hover:bg-white/20' : ''"
                    :style="hasImage ? {} : { color: article.category.color, borderColor: article.category.color + '40', backgroundColor: article.category.color + '12' }">
                    <span class="w-1.5 h-1.5 rounded-full" :style="{ background: article.category.color }" aria-hidden="true" />
                    {{ article.category.name }}
                </Link>

                <h1 class="text-3xl sm:text-[2.75rem] font-black tracking-tight leading-[1.1]" :class="title">{{ article.title }}</h1>

                <p v-if="article.excerpt" class="mt-4 text-[16px] leading-relaxed" :class="body">{{ article.excerpt }}</p>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-6 text-[13px]" :class="muted">
                    <div v-if="article.author" class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full overflow-hidden border shrink-0 flex items-center justify-center text-[10px] font-bold"
                            :class="hasImage ? 'border-white/25 bg-white/10 text-zinc-200' : dark ? 'border-zinc-700 bg-zinc-800 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                            <img v-if="article.author.avatar" :src="article.author.avatar" alt="" class="w-full h-full object-cover" />
                            <span v-else aria-hidden="true">{{ initials(article.author.name) }}</span>
                        </span>
                        <span class="font-semibold" :class="hasImage ? 'text-white' : dark ? 'text-zinc-200' : 'text-zinc-800'">{{ article.author.name }}</span>
                    </div>
                    <span aria-hidden="true" :class="divider">·</span>
                    <time :datetime="article.published_at_iso">{{ formatDate(article.published_at_iso, { dateStyle: 'long' }) }}</time>
                    <span aria-hidden="true" :class="divider">·</span>
                    <span class="flex items-center gap-1.5">
                        <Clock :size="12" :stroke-width="1.8" aria-hidden="true" />
                        {{ t('news.stat_minutes', { m: article.reading_time }) }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Eye :size="12" :stroke-width="1.8" aria-hidden="true" />
                        {{ article.views.toLocaleString() }}
                    </span>
                    <a href="#comments" class="flex items-center gap-1.5 font-semibold transition" :class="link"
                        :aria-label="`${t('news.comments')}: ${commentCount}`">
                        <MessageSquare :size="12" :stroke-width="2" aria-hidden="true" />
                        {{ commentCount }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
