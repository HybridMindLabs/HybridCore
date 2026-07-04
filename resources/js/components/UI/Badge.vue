<script setup lang="ts">
type Variant = 'default' | 'success' | 'warning' | 'danger' | 'info';

const props = withDefaults(
    defineProps<{ variant?: Variant }>(),
    { variant: 'default' },
);

const classes: Record<Variant, string> = {
    default: 'bg-zinc-900/60 text-zinc-400 border-zinc-800/70',
    success: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
    warning: 'bg-[#f59e0b]/10 text-[#f59e0b] border-[#f59e0b]/20',
    danger:  'bg-red-500/10 text-red-400 border-red-500/20',
    info:    'bg-[#3b82f6]/10 text-[#3b82f6] border-[#3b82f6]/20',
};
</script>

<template>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded-full border" :class="classes[props.variant]">
        <slot />
    </span>
</template>
