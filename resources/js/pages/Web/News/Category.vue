<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Eye, ChevronRight, Newspaper, ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface Category { id: number; name: string; slug: string; color: string; icon: string | null; description: string | null }
interface ArticleCard {
    id: number; title: string; slug: string; excerpt: string | null;
    featured_image_url: string | null; reading_time: number; views: number; published_at: string;
}
interface Pagination { data: ArticleCard[]; current_page: number; last_page: number; total: number }

const props = defineProps<{ category: Category; articles: Pagination }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <Head>
        <title>{{ category.name }} - News</title>
    </Head>
    <PublicLayout>

        <!-- ══════════════════════════════════════════════ HERO -->
        <div class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'">

            <!-- Glows + dot grid -->
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

            <div class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 py-14 sm:py-18">

                <!-- Breadcrumb -->
                <nav class="flex items-center gap-2 text-[12px] mb-7"
                    :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                    <Link :href="route('news.index')" class="transition"
                        :class="dark ? 'hover:text-zinc-300' : 'hover:text-zinc-700'">News</Link>
                    <ChevronRight :size="11" :stroke-width="2" />
                    <span class="font-semibold" :style="{ color: category.color }">{{ category.name }}</span>
                </nav>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-10">

                    <!-- Left -->
                    <div class="max-w-2xl">
                        <!-- Category badge -->
                        <div class="mb-5">
                            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest"
                                :style="{
                                    color: category.color,
                                    borderColor: category.color + '40',
                                    backgroundColor: category.color + '12'
                                }">
                                <span class="w-1.5 h-1.5 rounded-full" :style="{ background: category.color }" />
                                Category
                            </span>
                        </div>

                        <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05] mb-4"
                            :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                            {{ category.name }}
                        </h1>

                        <p v-if="category.description" class="mt-4 text-[15px] leading-relaxed max-w-lg mb-7"
                            :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                            {{ category.description }}
                        </p>

                        <!-- Stats pill -->
                        <div class="flex items-center gap-5 flex-wrap mb-7">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                                    :class="dark ? 'bg-zinc-900 border border-zinc-800' : 'bg-white border border-zinc-200 shadow-sm'">
                                    <Newspaper :size="14" :stroke-width="1.8" class="text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-[16px] font-black leading-none tabular-nums"
                                        :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ articles.total }}</p>
                                    <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5"
                                        :class="dark ? 'text-zinc-600' : 'text-zinc-400'">Articles</p>
                                </div>
                            </div>
                        </div>

                        <!-- Back link -->
                        <Link :href="route('news.index')"
                            class="inline-flex items-center gap-2 border font-bold text-[13px] px-5 py-2.5 rounded-xl transition"
                            :class="dark ? 'border-zinc-800 text-zinc-300 hover:text-white hover:border-zinc-600 hover:bg-white/[0.04]' : 'border-zinc-200 text-zinc-700 hover:border-zinc-300 hover:bg-white'">
                            <ArrowLeft :size="13" :stroke-width="2.2" />
                            All News
                        </Link>
                    </div>

                    <!-- Right: color accent block -->
                    <div class="hidden lg:flex items-center justify-center w-[280px] h-[180px] rounded-2xl border shrink-0"
                        :class="dark ? 'border-zinc-800/80' : 'border-zinc-200'"
                        :style="{ backgroundColor: category.color + '10' }">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-16 h-16 rounded-2xl flex items-center justify-center border"
                                :style="{ backgroundColor: category.color + '20', borderColor: category.color + '40' }">
                                <Newspaper :size="28" :stroke-width="1.4" :style="{ color: category.color }" />
                            </div>
                            <span class="text-[15px] font-black" :style="{ color: category.color }">{{ category.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ══════════════════════════════════════════ END HERO -->

        <!-- ══════════════════════════════════════════ ARTICLES -->
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-10">

            <div v-if="articles.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link v-for="a in articles.data" :key="a.id" :href="route('news.show', a.slug)"
                    class="group rounded-2xl border overflow-hidden flex flex-col transition-all duration-200"
                    :class="dark
                        ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
                        : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-md'">
                    <div class="relative overflow-hidden h-40 shrink-0"
                        :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
                        <img v-if="a.featured_image_url" :src="a.featured_image_url"
                            class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                            :class="dark ? 'opacity-80 group-hover:opacity-100' : ''" />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <Newspaper :size="24" :stroke-width="1.2"
                                :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                        </div>
                    </div>
                    <div class="p-4 flex flex-col gap-2 flex-1">
                        <h3 class="text-[14px] font-bold line-clamp-2 transition"
                            :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">
                            {{ a.title }}
                        </h3>
                        <p v-if="a.excerpt" class="text-[12px] line-clamp-2 flex-1"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ a.excerpt }}</p>
                        <div class="flex items-center gap-3 mt-auto text-[11px]"
                            :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            <span class="flex items-center gap-1"><Clock :size="10" />{{ a.reading_time }}m</span>
                            <span class="flex items-center gap-1"><Eye :size="10" />{{ a.views }}</span>
                            <span class="ml-auto">{{ a.published_at }}</span>
                        </div>
                    </div>
                </Link>
            </div>

            <div v-else class="text-center py-24"
                :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                <Newspaper :size="40" :stroke-width="1.2" class="mx-auto mb-3 opacity-40" />
                <p class="text-[14px]">No articles in this category yet.</p>
            </div>

            <!-- Pagination -->
            <div v-if="articles.last_page > 1" class="flex justify-center gap-1 mt-8">
                <Link v-for="p in articles.last_page" :key="p"
                    :href="route('news.category', { category: category.slug, page: p })"
                    class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition"
                    :class="p === articles.current_page
                        ? 'border-blue-500/40 bg-blue-500/10 text-blue-400'
                        : dark
                            ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-600'
                            : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300'">
                    {{ p }}
                </Link>
            </div>
        </div>

    </PublicLayout>
</template>
