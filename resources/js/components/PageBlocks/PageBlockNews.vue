<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

interface BlockArticle { title: string; excerpt: string | null; image: string | null; date: string | null; url: string }

defineProps<{ data: Record<string, any>; props: { articles: BlockArticle[] } }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const formatDate = (d: string) => new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <div v-if="props.articles.length">
        <h2 v-if="data.title" class="mb-4 text-[22px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ data.title }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-for="a in props.articles" :key="a.url" :href="a.url"
                class="group flex flex-col overflow-hidden rounded-xl ring-1 transition-colors"
                :class="dark ? 'bg-[#111113] ring-white/[0.06] hover:ring-white/15' : 'bg-white ring-zinc-200 hover:ring-zinc-300'">
                <img v-if="a.image" :src="a.image" alt="" loading="lazy" decoding="async" class="w-full aspect-[16/9] object-cover" />
                <div class="p-4 flex flex-col gap-1.5">
                    <span v-if="a.date" class="text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ formatDate(a.date) }}</span>
                    <h3 class="text-[15px] font-bold leading-snug group-hover:text-hc-accent transition-colors"
                        :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ a.title }}</h3>
                    <p v-if="a.excerpt" class="text-[13px] line-clamp-3" :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ a.excerpt }}</p>
                </div>
            </Link>
        </div>
    </div>
</template>
