<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import type { BlockButton } from './types';

const props = defineProps<{ buttons: BlockButton[]; center?: boolean }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const visible = computed(() => props.buttons.filter((b) => b.label && b.url));
const external = (url: string) => /^https?:\/\//i.test(url);
</script>

<template>
    <div v-if="visible.length" class="flex flex-wrap gap-3" :class="center ? 'justify-center' : ''">
        <a
            v-for="(b, i) in visible"
            :key="i"
            :href="b.url"
            :target="external(b.url) ? '_blank' : undefined"
            :rel="external(b.url) ? 'noopener noreferrer' : undefined"
            class="inline-flex items-center px-5 py-2.5 rounded-lg text-[14px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60"
            :class="b.style === 'secondary'
                ? (dark ? 'bg-white/[0.06] text-zinc-100 hover:bg-white/[0.12] ring-1 ring-white/10' : 'bg-zinc-100 text-zinc-900 hover:bg-zinc-200 ring-1 ring-zinc-200')
                : 'bg-hc-accent text-hc-base hover:bg-hc-accent-hover'"
        >{{ b.label }}</a>
    </div>
</template>
