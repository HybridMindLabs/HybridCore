<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';
import { proseClasses } from '@/components/PageBlocks/prose';
import BlockRenderer from '@/components/PageBlocks/BlockRenderer.vue';
import type { RenderedBlock } from '@/components/PageBlocks/types';

interface PageData { title: string; body: string | null; layout?: string; format?: string; blocks?: RenderedBlock[] }
interface SeoData { title: string; description: string | null; og_image: string | null; canonical: string }

const props = defineProps<{ page: PageData; seo: SeoData }>();

const isBlocks = computed(() => props.page.format === 'blocks');
// An opening hero with a title is the page heading (h1); the plain title would repeat it.
const showTitle = computed(() => {
    const first = props.page.blocks?.[0];
    return !isBlocks.value || first?.type !== 'hero' || !first.data.title;
});

const maxWidthClass = computed(() => {
    // Block pages lay out their own widths; text blocks stay readable on their own.
    if (isBlocks.value) return 'max-w-6xl';
    switch (props.page.layout) {
        case 'wide':     return 'max-w-5xl';
        case 'centered': return 'max-w-2xl';
        default:         return 'max-w-3xl';
    }
});

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta v-if="seo.description" name="description" :content="seo.description" />
        <meta property="og:title" :content="seo.title" />
        <meta v-if="seo.description" property="og:description" :content="seo.description" />
        <meta v-if="seo.og_image" property="og:image" :content="seo.og_image" />
        <link rel="canonical" :href="seo.canonical" />
    </Head>

    <PublicLayout>
        <main :class="[maxWidthClass, 'mx-auto px-4 sm:px-6 py-10 w-full']">

            <!-- Breadcrumb -->
            <Link
                :href="route('home')"
                class="inline-flex items-center gap-1.5 text-[13px] font-medium mb-8 transition-colors"
                :class="dark ? 'text-zinc-500 hover:text-zinc-300' : 'text-zinc-400 hover:text-zinc-500'"
            >
                <ChevronLeft :size="14" :stroke-width="2" />
                {{ t('navigation.back_to_home') }}
            </Link>

            <!-- Title + accent -->
            <template v-if="showTitle">
                <h1
                    class="text-[30px] font-black tracking-tight mb-3"
                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'"
                >{{ page.title }}</h1>
                <div class="h-1 w-12 rounded-full bg-blue-500 mb-8" />
            </template>

            <BlockRenderer v-if="isBlocks" :blocks="page.blocks ?? []" />

            <!-- Body -->
            <div
                v-else-if="page.body"
                class="prose prose-sm max-w-none leading-relaxed"
                :class="proseClasses(dark)"
                v-html="page.body"
            />
            <p v-else class="text-[14px] italic" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                No content yet.
            </p>

        </main>
    </PublicLayout>
</template>
