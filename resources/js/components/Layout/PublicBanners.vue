<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { safeUrl } from '@/lib/safeUrl';

/** The "you are impersonating" bar and the site-wide announcement banner. */
const { t } = useLocale();
const page = usePage<{
    impersonating: { name: string } | null;
    announcementBanner?: { text: string; type: string; link: string } | null;
}>();

const banner = computed(() => page.props.announcementBanner ?? null);

const tones: Record<string, string> = {
    info: 'bg-sky-500/15 text-sky-400 border-b border-sky-500/20',
    warning: 'bg-amber-500/15 text-amber-400 border-b border-amber-500/20',
    danger: 'bg-rose-500/15 text-rose-400 border-b border-rose-500/20',
    success: 'bg-emerald-500/15 text-emerald-400 border-b border-emerald-500/20',
};
</script>

<template>
    <div v-if="page.props.impersonating"
        class="bg-amber-500 text-amber-950 text-sm font-medium px-4 py-2 flex items-center justify-center gap-3 relative z-50">
        <span>{{ t('navigation.impersonating_as', { name: page.props.impersonating.name }) }}</span>
        <button type="button"
            class="bg-amber-950/90 text-amber-100 rounded-md px-3 py-1 text-xs font-semibold hover:bg-amber-950 transition-colors"
            @click="router.post(route('impersonation.stop'))">
            {{ t('navigation.stop_impersonating') }}
        </button>
    </div>

    <div v-if="banner"
        class="text-xs sm:text-sm font-medium px-4 py-2.5 flex items-center justify-center gap-2 relative z-40 transition-colors"
        :class="tones[banner.type] ?? tones.info"
        role="status">
        <span>{{ banner.text }}</span>
        <a v-if="banner.link" :href="safeUrl(banner.link)"
            class="underline font-semibold hover:opacity-80 transition-opacity ml-1"
            target="_blank" rel="noopener noreferrer">
            {{ t('navigation.learn_more') }} &rarr;
        </a>
    </div>
</template>
