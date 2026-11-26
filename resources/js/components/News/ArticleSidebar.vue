<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Clock, Copy, Newspaper, Tag } from '@lucide/vue';
import { computed } from 'vue';
import { useClipboard } from '@/composables/useClipboard';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';
import { initials, type Article, type ArticleSummary } from './articleTypes';

/** Contents, author, share, tags and related articles beside the body. */
defineProps<{ article: Article; related: ArticleSummary[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const { copied, copy } = useClipboard(2000);

const panel = computed(() => dark.value ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white');
const head = computed(() => dark.value ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50');
const headText = computed(() => dark.value ? 'text-zinc-400' : 'text-zinc-500');
</script>

<template>
    <aside class="flex flex-col gap-4 xl:sticky xl:top-20">
        <slot />

        <!-- Contents: the only way to skim a long piece. -->
        <nav v-if="article.headings.length > 1" :aria-label="t('news.toc_title')" class="rounded-2xl border overflow-hidden" :class="panel">
            <div class="border-b px-4 py-3" :class="head">
                <h2 class="text-[12px] font-bold uppercase tracking-widest" :class="headText">{{ t('news.toc_title') }}</h2>
            </div>
            <ol class="p-3 flex flex-col gap-0.5">
                <li v-for="heading in article.headings" :key="heading.id">
                    <a :href="`#${heading.id}`"
                        class="block py-1.5 px-2 rounded-lg text-[12px] leading-snug transition"
                        :class="[heading.level === 3 ? 'pl-5' : '', dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-white/[0.04]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50']">
                        {{ heading.text }}
                    </a>
                </li>
            </ol>
        </nav>

        <section v-if="article.author" class="rounded-2xl border overflow-hidden" :class="panel" :aria-label="t('news.sidebar_author')">
            <div class="border-b px-4 py-3" :class="head">
                <h2 class="text-[12px] font-bold uppercase tracking-widest" :class="headText">{{ t('news.sidebar_author') }}</h2>
            </div>
            <component :is="article.author.username ? Link : 'div'"
                :href="article.author.username ? route('profile.show', article.author.username) : undefined"
                class="p-4 flex items-center gap-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50"
                :class="article.author.username ? (dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50') : ''">
                <span class="w-10 h-10 rounded-full overflow-hidden border shrink-0 flex items-center justify-center font-bold text-[13px]"
                    :class="dark ? 'border-zinc-700 bg-zinc-800 text-zinc-400' : 'border-zinc-200 bg-zinc-100 text-zinc-500'">
                    <img v-if="article.author.avatar" :src="article.author.avatar" class="w-full h-full object-cover" alt="" loading="lazy" />
                    <span v-else aria-hidden="true">{{ initials(article.author.name) }}</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-[14px] font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ article.author.name }}</span>
                    <span v-if="article.author.username" class="block text-[12px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">@{{ article.author.username }}</span>
                </span>
            </component>
        </section>

        <section class="rounded-2xl border overflow-hidden" :class="panel" :aria-label="t('news.sidebar_share')">
            <div class="border-b px-4 py-3" :class="head">
                <h2 class="text-[12px] font-bold uppercase tracking-widest" :class="headText">{{ t('news.sidebar_share') }}</h2>
            </div>
            <div class="p-3">
                <button type="button"
                    class="w-full flex items-center justify-center gap-2 py-2 rounded-xl border text-[12px] font-semibold transition"
                    :class="copied
                        ? dark ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400' : 'border-emerald-400/40 bg-emerald-50 text-emerald-800'
                        : dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'"
                    @click="copy(article.canonical)">
                    <component :is="copied ? Check : Copy" :size="12" :stroke-width="2" aria-hidden="true" />
                    <span aria-live="polite">{{ copied ? t('news.copied') : t('news.copy_link') }}</span>
                </button>
            </div>
        </section>

        <section v-if="article.tags.length" class="rounded-2xl border overflow-hidden" :class="panel" :aria-label="t('news.sidebar_tags')">
            <div class="border-b px-4 py-3" :class="head">
                <h2 class="text-[12px] font-bold uppercase tracking-widest" :class="headText">{{ t('news.sidebar_tags') }}</h2>
            </div>
            <div class="p-3 flex flex-wrap gap-1.5">
                <Link v-for="tag in article.tags" :key="tag.slug" :href="route('news.tag', tag.slug)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-[11px] font-semibold transition"
                    :class="dark ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-200 hover:border-zinc-600' : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-400'">
                    <Tag :size="9" :stroke-width="2" aria-hidden="true" />#{{ tag.name }}
                </Link>
            </div>
        </section>

        <section v-if="related.length" class="rounded-2xl border overflow-hidden" :class="panel" :aria-label="t('news.sidebar_related')">
            <div class="border-b px-4 py-3" :class="head">
                <h2 class="text-[12px] font-bold uppercase tracking-widest" :class="headText">{{ t('news.sidebar_related') }}</h2>
            </div>
            <div class="p-3 flex flex-col gap-1">
                <Link v-for="a in related" :key="a.id" :href="route('news.show', a.slug)"
                    class="flex gap-3 items-center p-2 rounded-xl transition group" :class="dark ? 'hover:bg-white/[0.03]' : 'hover:bg-zinc-50'">
                    <span class="w-12 h-12 rounded-lg overflow-hidden border shrink-0 flex items-center justify-center"
                        :class="dark ? 'border-zinc-800/70 bg-zinc-900' : 'border-zinc-200 bg-zinc-100'">
                        <img v-if="a.featured_image_url" :src="a.featured_image_url" class="w-full h-full object-cover" alt="" loading="lazy" />
                        <Newspaper v-else :size="14" :stroke-width="1.5" aria-hidden="true" :class="dark ? 'text-zinc-600' : 'text-zinc-300'" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[12px] font-semibold line-clamp-2 transition-colors"
                            :class="dark ? 'text-zinc-300 group-hover:text-blue-300' : 'text-zinc-500 group-hover:text-blue-600'">{{ a.title }}</span>
                        <span class="text-[11px] mt-0.5 flex items-center gap-1" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                            <Clock :size="9" aria-hidden="true" />{{ t('news.read_time_short', { m: a.reading_time }) }}
                        </span>
                    </span>
                </Link>
            </div>
        </section>
    </aside>
</template>
