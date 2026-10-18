<script setup lang="ts">
withDefaults(defineProps<{
    class?: string;
    hover?: boolean;
}>(), { hover: false });
</script>

<template>
    <div
        class="bg-[#111113] dark:bg-hc-surface border border-zinc-800/70 dark:border-hc-border rounded-xl shadow-[0_2px_8px_rgba(0,0,0,0.3)] transition-colors duration-150"
        :class="[$props.class, hover ? 'hc-card-hover cursor-pointer' : '']"
    >
        <slot />
    </div>
</template>
