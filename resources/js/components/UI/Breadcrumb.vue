<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

defineProps<{
    items: { label: string; href?: string }[];
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <!-- Colours were dark-only. The current-page item rendered as text-zinc-300,
         which is all but invisible on a light background — and this component is
         used on every public page, not just the dark ones. -->
    <nav class="flex items-center gap-1.5 text-[12px] mb-4 flex-wrap" :aria-label="t('navigation.breadcrumb')">
        <template v-for="(item, i) in items" :key="i">
            <Link v-if="item.href" :href="item.href"
                class="transition-colors font-medium rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-600 hover:text-zinc-900'">
                {{ item.label }}
            </Link>
            <span v-else class="font-semibold" aria-current="page"
                :class="dark ? 'text-zinc-200' : 'text-zinc-900'">{{ item.label }}</span>
            <ChevronRight v-if="i < items.length - 1" :size="11" :stroke-width="2.5" aria-hidden="true"
                class="shrink-0" :class="dark ? 'text-zinc-700' : 'text-zinc-400'" />
        </template>
    </nav>
</template>
