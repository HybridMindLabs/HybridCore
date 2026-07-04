<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock, Eye, Newspaper } from '@lucide/vue';

interface NewsCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number;
    published_at: string;
    category: { id: number; name: string; slug: string; color: string } | null;
    author: { id: number; name: string } | null;
}
defineProps<{ article: NewsCard; dark: boolean; size?: 'large' | 'normal' | 'compact' }>();
</script>

<template>
    <!-- COMPACT: horizontal row (thumbnail + text) -->
    <Link v-if="size === 'compact'"
        :href="route('news.show', article.slug)"
        class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200"
        :class="dark
            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/50 hover:bg-[#16161a]'
            : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-sm'">

        <div class="relative overflow-hidden rounded-lg shrink-0 w-14 h-14"
            :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
            <img v-if="article.featured_image_url" :src="article.featured_image_url"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                :class="dark ? 'opacity-70 group-hover:opacity-90' : ''" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <Newspaper :size="13" :stroke-width="1.5" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
            </div>
        </div>

        <div class="min-w-0 flex flex-col gap-0.5 flex-1">
            <span v-if="article.category" class="text-[10px] font-bold" :style="{ color: article.category.color }">
                {{ article.category.name }}
            </span>
            <h4 class="text-[13px] font-semibold leading-snug line-clamp-2 transition-colors"
                :class="dark ? 'text-zinc-200 group-hover:text-blue-100' : 'text-zinc-800 group-hover:text-blue-700'">
                {{ article.title }}
            </h4>
            <div class="flex items-center gap-2 text-[11px]"
                :class="dark ? 'text-zinc-700' : 'text-zinc-400'">
                <span class="flex items-center gap-0.5"><Clock :size="9" />{{ article.reading_time }}m</span>
                <span>·</span>
                <span>{{ article.published_at }}</span>
            </div>
        </div>
    </Link>

    <!-- NORMAL: vertical card -->
    <Link v-else-if="size === 'normal'"
        :href="route('news.show', article.slug)"
        class="group rounded-2xl border overflow-hidden flex flex-col transition-all duration-200"
        :class="dark
            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/50'
            : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-md'">

        <div class="relative overflow-hidden h-40 shrink-0"
            :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
            <img v-if="article.featured_image_url" :src="article.featured_image_url"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                :class="dark ? 'opacity-75 group-hover:opacity-95' : ''" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <Newspaper :size="24" :stroke-width="1.2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
            </div>
            <div v-if="article.category" class="absolute top-2.5 left-2.5">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full backdrop-blur-sm"
                    :style="{ backgroundColor: article.category.color+'30', color: article.category.color, border: `1px solid ${article.category.color}40` }">
                    {{ article.category.name }}
                </span>
            </div>
        </div>

        <div class="p-4 flex flex-col gap-1.5 flex-1">
            <h3 class="text-[14px] font-bold leading-snug line-clamp-2 transition-colors"
                :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">
                {{ article.title }}
            </h3>
            <p v-if="article.excerpt" class="text-[12px] line-clamp-2 flex-1"
                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ article.excerpt }}</p>
            <div class="flex items-center gap-3 mt-auto text-[11px]"
                :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                <span class="flex items-center gap-1"><Clock :size="10" />{{ article.reading_time }}m</span>
                <span class="flex items-center gap-1"><Eye :size="10" />{{ article.views }}</span>
                <span class="ml-auto">{{ article.published_at }}</span>
            </div>
        </div>
    </Link>

    <!-- LARGE: big featured card with tall image -->
    <Link v-else
        :href="route('news.show', article.slug)"
        class="group relative overflow-hidden rounded-2xl border flex flex-col transition-all duration-300"
        :class="dark
            ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/50'
            : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-lg'">

        <div class="relative overflow-hidden h-[220px] shrink-0"
            :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
            <img v-if="article.featured_image_url" :src="article.featured_image_url"
                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500"
                :class="dark ? 'opacity-75 group-hover:opacity-95' : ''" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <Newspaper :size="32" :stroke-width="1.2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
            </div>
            <div class="absolute inset-0"
                :class="dark ? 'bg-gradient-to-t from-[#111113] via-transparent to-transparent' : 'bg-gradient-to-t from-white/50 via-transparent to-transparent'" />
            <div v-if="article.category" class="absolute top-3 left-3">
                <span class="text-[11px] font-bold px-2.5 py-1 rounded-full backdrop-blur-sm"
                    :style="{ backgroundColor: article.category.color+'30', color: article.category.color, border: `1px solid ${article.category.color}40` }">
                    {{ article.category.name }}
                </span>
            </div>
        </div>

        <div class="p-5 flex flex-col gap-2 flex-1">
            <h3 class="text-[18px] font-black leading-snug line-clamp-2 transition-colors"
                :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">
                {{ article.title }}
            </h3>
            <p v-if="article.excerpt" class="text-[13px] leading-relaxed line-clamp-2"
                :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ article.excerpt }}</p>
            <div class="flex items-center gap-3 mt-auto pt-1 text-[12px]"
                :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                <span v-if="article.author" class="font-semibold" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    {{ article.author.name }}
                </span>
                <span class="flex items-center gap-1"><Clock :size="11" />{{ article.reading_time }}m</span>
                <span class="flex items-center gap-1"><Eye :size="11" />{{ article.views }}</span>
                <span class="ml-auto text-[11px]">{{ article.published_at }}</span>
            </div>
        </div>
    </Link>
</template>