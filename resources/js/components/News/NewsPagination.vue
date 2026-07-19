<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    /** Builds the href for a page number. */
    href: (page: number) => string;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

/**
 * A window around the current page. The category and tag listings rendered one
 * link per page, so an archive of a few hundred pages emitted a few hundred
 * anchors — unusable to read and slow to render.
 */
const pageWindow = computed(() => {
    const span = 2;
    const from = Math.max(1, props.currentPage - span);
    const to = Math.min(props.lastPage, props.currentPage + span);

    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
});

const edge = computed(() =>
    dark.value
        ? 'border-zinc-800/70 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600'
        : 'border-zinc-200 text-zinc-500 hover:text-zinc-800 hover:border-zinc-300',
);
</script>

<template>
    <nav v-if="lastPage > 1" :aria-label="t('news.pagination_label')" class="flex flex-col items-center gap-2 mt-8">
        <div class="flex items-center gap-1">
            <Link
                v-if="currentPage > 1"
                :href="href(currentPage - 1)"
                rel="prev"
                class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                :class="edge"
            >
                <ChevronLeft :size="13" :stroke-width="2" aria-hidden="true" />
                {{ t('news.prev') }}
            </Link>

            <Link
                v-if="pageWindow[0] > 1"
                :href="href(1)"
                :aria-label="t('news.page_number', { page: 1 })"
                class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                :class="edge"
            >1</Link>
            <span v-if="pageWindow[0] > 2" aria-hidden="true" class="px-1" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">…</span>

            <Link
                v-for="p in pageWindow"
                :key="p"
                :href="href(p)"
                :aria-current="p === currentPage ? 'page' : undefined"
                :aria-label="t('news.page_number', { page: p })"
                class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                :class="p === currentPage
                    ? dark ? 'border-blue-500/40 bg-blue-500/10 text-blue-400' : 'border-blue-300 bg-blue-50 text-blue-700'
                    : edge"
            >{{ p }}</Link>

            <span
                v-if="pageWindow[pageWindow.length - 1] < lastPage - 1"
                aria-hidden="true"
                class="px-1"
                :class="dark ? 'text-zinc-500' : 'text-zinc-400'"
            >…</span>
            <Link
                v-if="pageWindow[pageWindow.length - 1] < lastPage"
                :href="href(lastPage)"
                :aria-label="t('news.page_number', { page: lastPage })"
                class="w-9 h-9 flex items-center justify-center rounded-xl border text-[13px] font-bold transition
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                :class="edge"
            >{{ lastPage }}</Link>

            <Link
                v-if="currentPage < lastPage"
                :href="href(currentPage + 1)"
                rel="next"
                class="h-9 px-3 flex items-center gap-1 rounded-xl border text-[12px] font-semibold transition
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                :class="edge"
            >
                {{ t('news.next') }}
                <ChevronRight :size="13" :stroke-width="2" aria-hidden="true" />
            </Link>
        </div>

        <p class="text-[11px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
            {{ t('news.showing', { current: currentPage, last: lastPage }) }}
        </p>
    </nav>
</template>
