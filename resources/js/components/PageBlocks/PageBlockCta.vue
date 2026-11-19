<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import BlockButtons from './BlockButtons.vue';

defineProps<{ data: Record<string, any> }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <div class="rounded-2xl px-6 py-10 sm:px-10 text-center ring-1"
        :class="dark ? 'bg-hc-accent/[0.07] ring-hc-accent/20' : 'bg-hc-accent/[0.08] ring-hc-accent/25'">
        <h2 v-if="data.title" class="text-[24px] sm:text-[28px] font-black tracking-tight" :class="dark ? 'text-zinc-50' : 'text-zinc-900'">{{ data.title }}</h2>
        <p v-if="data.text" class="mx-auto mt-3 max-w-xl text-[15px] leading-relaxed whitespace-pre-line" :class="dark ? 'text-zinc-300' : 'text-zinc-600'">{{ data.text }}</p>
        <BlockButtons class="mt-6" :buttons="data.buttons ?? []" center />
    </div>
</template>
