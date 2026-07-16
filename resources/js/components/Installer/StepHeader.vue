<script setup lang="ts">
import type { Component } from 'vue';

/**
 * The heading every installer step opens with: what this step is, and what it
 * will do. Shared so all six steps read as one thing rather than six.
 */
defineProps<{
    icon: Component;
    title: string;
    description: string;
}>();
</script>

<template>
    <div class="mb-6">
        <div class="flex items-center gap-2.5 mb-1.5">
            <div class="w-7 h-7 rounded-lg bg-blue-500/10 border border-blue-500/25 flex items-center justify-center shrink-0">
                <component :is="icon" :size="14" :stroke-width="1.75" class="text-blue-400" />
            </div>
            <h2 class="text-zinc-100 text-xl font-black tracking-tight">{{ title }}</h2>
        </div>
        <p class="text-zinc-500 text-sm leading-relaxed max-w-xl">{{ description }}</p>
    </div>
</template>
