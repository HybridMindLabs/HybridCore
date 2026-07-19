<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

interface PageData { title: string; body: string | null; layout?: string; format?: string }
interface SeoData { title: string; description: string | null; og_image: string | null; canonical: string }

const props = defineProps<{ page: PageData; seo: SeoData }>();

const maxWidthClass = computed(() => {
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
            <h1
                class="text-[30px] font-black tracking-tight mb-3"
                :class="dark ? 'text-zinc-100' : 'text-zinc-900'"
            >{{ page.title }}</h1>
            <div class="h-1 w-12 rounded-full bg-blue-500 mb-8" />

            <!-- Body -->
            <div
                v-if="page.body"
                class="prose prose-sm max-w-none leading-relaxed"
                :class="dark
                    ? '[&]:text-zinc-400 [&_h1]:text-zinc-100 [&_h2]:text-zinc-100 [&_h2]:font-bold [&_h2]:mt-8 [&_h2]:mb-3 [&_h3]:text-zinc-200 [&_h3]:font-semibold [&_h3]:mt-6 [&_h3]:mb-2 [&_h4]:text-zinc-300 [&_h4]:font-medium [&_p]:mb-4 [&_a]:text-blue-400 [&_a]:underline [&_a]:underline-offset-2 [&_strong]:text-zinc-200 [&_em]:text-zinc-300 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul>li]:mb-1.5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-4 [&_ol>li]:mb-1.5 [&_blockquote]:border-l-2 [&_blockquote]:border-blue-500/40 [&_blockquote]:pl-4 [&_blockquote]:text-zinc-500 [&_blockquote]:italic [&_blockquote]:my-4 [&_code]:bg-zinc-800/80 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-blue-400 [&_code]:text-xs [&_code]:font-mono [&_pre]:bg-zinc-800/80 [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:mb-4 [&_hr]:border-zinc-800/60 [&_hr]:my-8 [&_table]:w-full [&_th]:text-left [&_th]:text-zinc-500 [&_th]:font-medium [&_th]:pb-2 [&_th]:border-b [&_th]:border-zinc-800/60 [&_td]:py-2 [&_td]:border-b [&_td]:border-zinc-800/40'
                    : '[&]:text-zinc-500 [&_h1]:text-zinc-900 [&_h2]:text-zinc-900 [&_h2]:font-bold [&_h2]:mt-8 [&_h2]:mb-3 [&_h3]:text-zinc-800 [&_h3]:font-semibold [&_h3]:mt-6 [&_h3]:mb-2 [&_h4]:text-zinc-500 [&_h4]:font-medium [&_p]:mb-4 [&_a]:text-blue-600 [&_a]:underline [&_a]:underline-offset-2 [&_strong]:text-zinc-900 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul>li]:mb-1.5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-4 [&_ol>li]:mb-1.5 [&_blockquote]:border-l-2 [&_blockquote]:border-blue-400/50 [&_blockquote]:pl-4 [&_blockquote]:text-zinc-400 [&_blockquote]:italic [&_blockquote]:my-4 [&_code]:bg-zinc-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-blue-600 [&_code]:text-xs [&_code]:font-mono [&_pre]:bg-zinc-100 [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:mb-4 [&_hr]:border-zinc-200 [&_hr]:my-8 [&_table]:w-full [&_th]:text-left [&_th]:text-zinc-500 [&_th]:font-medium [&_th]:pb-2 [&_th]:border-b [&_th]:border-zinc-200 [&_td]:py-2 [&_td]:border-b [&_td]:border-zinc-100'"
                v-html="page.body"
            />
            <p v-else class="text-[14px] italic" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                No content yet.
            </p>

        </main>
    </PublicLayout>
</template>
