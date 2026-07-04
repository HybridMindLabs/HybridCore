<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    icon?: unknown;
}>();
</script>

<template>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div
                v-if="icon"
                class="w-9 h-9 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center shrink-0"
            >
                <component :is="icon" :size="17" :stroke-width="1.75" class="text-blue-400" />
            </div>
            <div>
                <h1 class="text-zinc-100 text-xl font-semibold tracking-tight">{{ title }}</h1>
                <p v-if="description" class="text-zinc-500 text-sm mt-0.5">{{ description }}</p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
