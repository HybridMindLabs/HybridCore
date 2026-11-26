<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

/** A titled card on a server's page; the header's right side is the `aside` slot. */
defineProps<{ title: string; icon?: Component }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
</script>

<template>
    <section class="rounded-xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
        :aria-label="title">
        <div class="px-5 py-3.5 border-b flex items-center justify-between gap-3"
            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <div class="flex items-center gap-2 min-w-0">
                <component :is="icon" v-if="icon" :size="13" :stroke-width="1.8" class="text-zinc-500 shrink-0" aria-hidden="true" />
                <h2 class="text-[13px] font-semibold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ title }}</h2>
            </div>
            <slot name="aside" />
        </div>
        <slot />
    </section>
</template>
