<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { computed } from 'vue';
import { useClipboard } from '@/composables/useClipboard';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{ address: string }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const { copied, copy } = useClipboard();

const label = computed(() => t('home.copy_address', { address: props.address }));
</script>

<template>
    <button
        type="button"
        class="w-7 h-7 flex items-center justify-center rounded-md transition shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
        :class="copied
            ? 'text-emerald-500'
            : dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.06]' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/70'"
        :aria-label="label"
        :title="copied ? t('home.copied') : label"
        @click="copy(address)"
    >
        <component :is="copied ? Check : Copy" :size="13" :stroke-width="2" aria-hidden="true" />
    </button>
</template>
