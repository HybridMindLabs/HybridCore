<script setup lang="ts">
import { Info } from '@lucide/vue';
import type { Component } from 'vue';
import Tooltip from '@/components/UI/Tooltip.vue';

/** A dashboard panel: tinted icon, title with an explanation, subtitle, and the content. */
defineProps<{
    title: string;
    subtitle: string;
    help?: string;
    icon: Component;
    /** Literal class strings so Tailwind keeps them, e.g. "bg-blue-500/10 text-blue-400". */
    tone: string;
}>();
</script>

<template>
    <section class="hc-hero-in rounded-xl border border-zinc-800/70 bg-admin-surface p-5" :aria-label="title">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" :class="tone" aria-hidden="true">
                <component :is="icon" :size="13" :stroke-width="2" />
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="text-[13px] font-black text-zinc-100 flex items-center gap-1.5">
                    {{ title }}
                    <Tooltip v-if="help" :text="help"><Info :size="12" :stroke-width="2" class="text-zinc-500 hover:text-zinc-300 transition-colors" /></Tooltip>
                </h2>
                <p class="text-[11px] text-zinc-500">{{ subtitle }}</p>
            </div>
            <slot name="aside" />
        </div>
        <slot />
    </section>
</template>
