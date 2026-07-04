<script setup lang="ts">
withDefaults(defineProps<{ text: string; position?: 'top' | 'bottom' }>(), {
    position: 'top',
});
</script>

<template>
    <span class="relative inline-flex group">
        <slot />
        <span
            class="pointer-events-none absolute left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-[#0d1420] border border-zinc-800/70
                   text-[#e2e8f0] text-[11px] font-medium px-2 py-1 opacity-0 scale-95 transition-all duration-100 z-50 shadow-lg shadow-black/40
                   group-hover:opacity-100 group-hover:scale-100"
            :class="position === 'top' ? 'bottom-full mb-1.5' : 'top-full mt-1.5'"
        >
            {{ text }}
        </span>
    </span>
</template>
