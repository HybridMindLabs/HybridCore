<script setup lang="ts">
withDefaults(defineProps<{
    class?: string;
    hover?: boolean;
}>(), { hover: false });
</script>

<template>
    <div
        class="bg-[#111113] border border-zinc-800/70 rounded-xl"
        :class="[$props.class, hover ? 'hc-card-hover cursor-pointer' : '']"
    >
        <slot />
    </div>
</template>
