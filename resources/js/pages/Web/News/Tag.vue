<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Eye, Hash, Newspaper } from '@lucide/vue';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import { useTheme } from '@/composables/useTheme';

interface TagModel { id: number; name: string; slug: string }
interface ArticleCard { id: number; title: string; slug: string; excerpt: string | null; featured_image_url: string | null; reading_time: number; views: number; published_at: string; category: { name: string; slug: string; color: string } | null }
interface Pagination { data: ArticleCard[]; current_page: number; last_page: number; total: number }

const props = defineProps<{ tag: TagModel; articles: Pagination }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <Head>
        <title>#{{ tag.name }} - News</title>
    </Head>
    <PublicLayout>

        <!-- ═══════════════════════════════════════════════════ HERO -->
        <div class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'">

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
                <Breadcrumb :items="[
                    { label: 'Home', href: route('home') },
                    { label: 'News', href: route('news.index') },
                    { label: '#' + tag.name },
                ]" />

                <div class="max-w-2xl">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-[11px] font-bold uppercase tracking-widest mb-6"
                        :class="dark ? 'border-blue-500/25 bg-blue-500/8 text-blue-400' : 'border-blue-400/30 bg-blue-50 text-blue-600'"
                    >
                        <Hash :size="11" :stroke-width="2.2" />
                        Tag · {{ articles.total }} article{{ articles.total !== 1 ? 's' : '' }}
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.05]"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        <span class="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">#{{ tag.name }}</span>
                    </h1>
                </div>
            </div>
        </div>
        <!-- ═════════════════════════════════════════════ END HERO -->

        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-8">
            <!-- Grid -->
            <div v-if="articles.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Link v-for="a in articles.data" :key="a.id" :href="route('news.show', a.slug)"
                    class="group rounded-2xl border overflow-hidden flex flex-col transition-all duration-200"
                    :class="dark
                        ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700/60'
                        : 'border-zinc-200 bg-white hover:border-zinc-300 hover:shadow-md shadow-sm'">
                    <div class="relative overflow-hidden h-40 shrink-0" :class="dark ? 'bg-zinc-900/60' : 'bg-zinc-100'">
                        <img v-if="a.featured_image_url" :src="a.featured_image_url"
                            class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                            :class="dark ? 'opacity-80 group-hover:opacity-100' : ''" />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <Newspaper :size="24" :stroke-width="1.2" :class="dark ? 'text-zinc-800' : 'text-zinc-300'" />
                        </div>
                    </div>
                    <div class="p-4 flex flex-col gap-2 flex-1">
                        <div v-if="a.category" class="mb-0.5">
                            <span class="text-[10px] font-bold" :style="{ color: a.category.color }">{{ a.category.name }}</span>
                        </div>
                        <h3 class="text-[14px] font-bold line-clamp-2 transition"
                            :class="dark ? 'text-zinc-100 group-hover:text-blue-100' : 'text-zinc-900 group-hover:text-blue-700'">{{ a.title }}</h3>
                        <p v-if="a.excerpt" class="text-[12px] line-clamp-2 flex-1" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ a.excerpt }}</p>
                        <div class="flex items-center gap-3 mt-auto text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                            <span class="flex items-center gap-1"><Clock :size="10" />{{ a.reading_time }}m</span>
                            <span class="flex items-center gap-1"><Eye :size="10" />{{ a.views }}</span>
                        </div>
                    </div>
                </Link>
            </div>
            <div v-else class="rounded-2xl border p-16 text-center"
                :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white'">
                <Hash :size="26" :stroke-width="1.5" class="mx-auto mb-3" :class="dark ? 'text-zinc-700' : 'text-zinc-300'" />
                <p class="text-[13px] font-semibold" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">No articles with this tag.</p>
            </div>

            <div v-if="articles.last_page > 1" class="flex justify-center gap-1 mt-8">
                <Link v-for="p in articles.last_page" :key="p"
                    :href="route('news.tag', { tag: tag.slug, page: p })"
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
