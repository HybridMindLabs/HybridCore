<script setup lang="ts">
import { computed, onMounted, watchEffect } from 'vue';
import PublicBanners from '@/components/Layout/PublicBanners.vue';
import PublicFooter from '@/components/Layout/PublicFooter.vue';
import PublicHeader from '@/components/Layout/PublicHeader.vue';
import ToastManager from '@/components/UI/ToastManager.vue';
import { useFlashToast } from '@/composables/useFlashToast';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';

const { t, currentLocale, localeDirection } = useLocale();
const { theme, init: initTheme } = useTheme();
const dark = computed(() => theme.value === 'dark');

useFlashToast();

// Inertia swaps the page body/head without replacing the root <html> element.
// Keep its language contract in sync after an in-app locale switch so screen
// readers immediately use the correct pronunciation and reading direction.
watchEffect(() => {
    if (typeof document === 'undefined') return;
    document.documentElement.lang = currentLocale.value.replace('_', '-');
    document.documentElement.dir = localeDirection.value;
});

onMounted(initTheme);
</script>

<template>
    <div class="min-h-screen flex flex-col transition-colors duration-200"
        :class="dark ? 'bg-[#09090b] text-zinc-100' : 'bg-zinc-100 text-zinc-900'">
        <PublicBanners />

        <!-- First focusable element on the page, so keyboard and screen-reader
             users can skip the navigation on every page — WCAG 2.4.1. -->
        <a href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[200]
                   focus:rounded-xl focus:px-4 focus:py-2.5 focus:text-[13px] focus:font-bold
                   focus:outline-2 focus:outline-offset-2 focus:outline-hc-accent"
            :class="dark ? 'focus:bg-zinc-900 focus:text-zinc-100' : 'focus:bg-white focus:text-zinc-900 focus:shadow-lg'">{{ t('navigation.skip_to_content') }}</a>

        <PublicHeader />

        <ExtensionSlot name="layout.top" />

        <main id="main-content" tabindex="-1" class="flex-1">
            <slot />
        </main>

        <PublicFooter />
    </div>

    <ToastManager />
</template>
