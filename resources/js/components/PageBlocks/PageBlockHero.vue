<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import BlockButtons from './BlockButtons.vue';

// The opening hero is the page heading; further down it is a section heading.
const props = defineProps<{ data: Record<string, any>; index?: number }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const center = computed(() => props.data.align === 'center');
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl ring-1 px-6 py-14 sm:px-12 sm:py-20"
        :class="[
            dark ? 'bg-[#111113] ring-white/[0.06]' : 'bg-zinc-50 ring-zinc-200',
            center ? 'text-center' : '',
        ]"
    >
        <template v-if="data.image">
            <img :src="data.image" alt="" class="absolute inset-0 w-full h-full object-cover" loading="eager" fetchpriority="high" decoding="async" />
            <div class="absolute inset-0" :class="dark
                ? 'bg-gradient-to-r from-[#0b0b0d] via-[#0b0b0d]/85 to-[#0b0b0d]/40'
                : 'bg-gradient-to-r from-white via-white/85 to-white/40'" />
        </template>

        <div class="relative max-w-2xl" :class="center ? 'mx-auto' : ''">
            <p v-if="data.eyebrow" class="mb-3 text-[12px] font-bold uppercase tracking-[0.14em] text-hc-accent">{{ data.eyebrow }}</p>
            <component :is="index === 0 ? 'h1' : 'h2'" v-if="data.title" class="text-[32px] sm:text-[44px] leading-[1.05] font-black tracking-tight"
                :class="dark ? 'text-zinc-50' : 'text-zinc-900'">{{ data.title }}</component>
            <p v-if="data.subtitle" class="mt-4 text-[16px] leading-relaxed whitespace-pre-line"
                :class="dark ? 'text-zinc-300' : 'text-zinc-600'">{{ data.subtitle }}</p>
            <BlockButtons class="mt-8" :buttons="data.buttons ?? []" :center="center" />
        </div>
    </div>
</template>
