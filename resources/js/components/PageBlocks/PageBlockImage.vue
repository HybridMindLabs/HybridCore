<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps<{ data: Record<string, any> }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const width = computed(() => ({ normal: 'max-w-3xl', wide: 'max-w-5xl', full: 'max-w-none' }[props.data.width as string] ?? 'max-w-3xl'));
</script>

<template>
    <figure v-if="data.url" class="mx-auto" :class="width">
        <img :src="data.url" :alt="data.alt || ''" loading="lazy" decoding="async"
            class="w-full h-auto rounded-xl ring-1" :class="dark ? 'ring-white/[0.06]' : 'ring-zinc-200'" />
        <figcaption v-if="data.caption" class="mt-2.5 text-center text-[13px]"
            :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ data.caption }}</figcaption>
    </figure>
</template>
