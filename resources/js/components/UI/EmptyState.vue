<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    icon?: unknown;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-xl bg-zinc-900/60 border border-zinc-800/70 flex items-center justify-center mb-4">
            <component
                v-if="icon"
                :is="icon"
                :size="24"
                :stroke-width="1.5"
                class="text-zinc-500"
            />
        </div>
        <p class="text-zinc-100 text-sm font-medium">{{ title ?? 'Nothing here yet' }}</p>
        <p v-if="description" class="text-zinc-500 text-xs mt-1 max-w-xs">{{ description }}</p>
        <p v-else-if="$slots.description" class="text-zinc-500 text-xs mt-1 max-w-xs"><slot name="description" /></p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
