<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Newspaper, Eye, Clock } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import type { NewsCard } from './types';

const props = defineProps<{ news: NewsCard[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const lead = computed(() => props.news[0] ?? null);
const rest = computed(() => props.news.slice(1, 4));
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-end justify-between gap-4 mb-4">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-1 h-9 rounded-full bg-blue-500 shrink-0 mt-0.5" aria-hidden="true" />
                <div class="min-w-0">
                    <h2 class="text-[20px] font-black tracking-tight"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('home.latest_news') }}</h2>
                    <p class="text-[12.5px] mt-0.5 leading-snug text-zinc-500">{{ t('home.news_hint') }}</p>
                </div>
            </div>
            <!-- Always visible: on mobile this used to be hidden, leaving
                 the bottom button as the only route to the archive. -->
            <Link :href="route('news.index')"
                class="group flex items-center gap-1.5 text-[12px] font-semibold shrink-0 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 rounded-lg px-1"
                :class="dark ? 'text-zinc-500 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'">
                {{ t('home.all_articles') }}
                <ArrowRight :size="12" :stroke-width="2"
                    class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">

            <!-- Lead story: the newest article gets the room to be read. -->
            <Link v-if="lead" :href="route('news.show', lead.slug)"
                class="hc-reveal group flex flex-col rounded-xl border overflow-hidden transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="dark ? 'border-zinc-800/70 bg-[#111113] hover:border-zinc-700' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)] hover:shadow-[0_6px_20px_rgba(0,0,0,0.09)]'">

                <div class="relative aspect-[16/9] overflow-hidden"
                    :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
                    <img v-if="lead.featured_image_url" :src="lead.featured_image_url"
                        :alt="lead.title" loading="lazy" decoding="async"
                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.04]" />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <Newspaper :size="28" :stroke-width="1.2"
                            :class="dark ? 'text-zinc-500' : 'text-zinc-300'" aria-hidden="true" />
                    </div>

                    <span v-if="lead.category"
                        class="absolute top-3 left-3 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-bold uppercase tracking-wide backdrop-blur-md"
                        :style="{ background: lead.category.color + '26', color: lead.category.color, boxShadow: `inset 0 0 0 1px ${lead.category.color}45` }">
                        {{ lead.category.name }}
                    </span>
                </div>

                <div class="flex flex-col flex-1 p-4">
                    <h3 class="text-[17px] font-black leading-snug line-clamp-2 transition-colors"
                        :class="dark ? 'text-zinc-100 group-hover:text-blue-300' : 'text-zinc-900 group-hover:text-blue-700'">
                        {{ lead.title }}
                    </h3>
                    <p v-if="lead.excerpt" class="text-[13px] leading-relaxed line-clamp-2 mt-1.5"
                        :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                        {{ lead.excerpt }}
                    </p>
                    <div class="flex items-center gap-3 mt-4 pt-3 border-t text-[11px] text-zinc-500"
                        :class="dark ? 'border-zinc-800/60' : 'border-zinc-200'">
                        <span v-if="lead.author" class="font-semibold truncate">{{ lead.author.name }}</span>
                        <span class="flex items-center gap-1 shrink-0" :title="t('home.views_hint')">
                            <Eye :size="11" :stroke-width="1.8" aria-hidden="true" />{{ lead.views }}
                        </span>
                        <span class="flex items-center gap-1 shrink-0">
                            <Clock :size="11" :stroke-width="1.8" aria-hidden="true" />
                            {{ t('news.read_time_short', { m: lead.reading_time }) }}
                        </span>
                        <span class="ml-auto shrink-0">{{ lead.published_at }}</span>
                    </div>
                </div>
            </Link>

            <!-- The rest, compact -->
            <div class="hc-reveal flex flex-col rounded-xl border overflow-hidden divide-y" style="animation-delay:0.08s"
                :class="dark ? 'border-zinc-800/70 bg-[#111113] divide-zinc-800/50' : 'border-zinc-200 bg-white divide-zinc-100 shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                <Link v-for="a in rest" :key="a.id"
                    :href="route('news.show', a.slug)"
                    class="group flex gap-3.5 p-3.5 flex-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                    :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50'">

                    <div class="relative shrink-0 w-[92px] h-[68px] rounded-lg overflow-hidden"
                        :class="dark ? 'bg-zinc-900' : 'bg-zinc-100'">
                        <img v-if="a.featured_image_url" :src="a.featured_image_url"
                            :alt="a.title" loading="lazy" decoding="async"
                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <Newspaper :size="16" :stroke-width="1.3"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-300'" aria-hidden="true" />
                        </div>
                        <div v-if="a.category" class="absolute bottom-0 left-0 right-0 h-0.5"
                            :style="{ background: a.category.color }" aria-hidden="true" />
                    </div>

                    <div class="min-w-0 flex flex-col flex-1">
                        <span v-if="a.category" class="text-[10.5px] font-bold uppercase tracking-wide mb-1"
                            :style="{ color: a.category.color }">
                            {{ a.category.name }}
                        </span>
                        <h3 class="text-[13.5px] font-bold leading-snug line-clamp-2 transition-colors"
                            :class="dark ? 'text-zinc-100 group-hover:text-blue-300' : 'text-zinc-900 group-hover:text-blue-700'">
                            {{ a.title }}
                        </h3>
                        <div class="flex items-center gap-2.5 mt-auto pt-2 text-[10.5px] text-zinc-500">
                            <span class="flex items-center gap-1">
                                <Clock :size="10" :stroke-width="1.8" aria-hidden="true" />
                                {{ t('news.read_time_short', { m: a.reading_time }) }}
                            </span>
                            <span class="ml-auto">{{ a.published_at }}</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>
