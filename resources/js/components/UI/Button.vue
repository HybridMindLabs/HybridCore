<script setup lang="ts">
type Variant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'accent';
type Size = 'sm' | 'md' | 'lg';

withDefaults(defineProps<{
    variant?: Variant;
    size?: Size;
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
}>(), {
    variant: 'primary',
    size: 'md',
    disabled: false,
    type: 'button',
});

const variantClass: Record<Variant, string> = {
    primary:   'bg-gradient-to-br from-[#4f8fff] to-[#2f6ff0] text-white font-bold shadow-lg shadow-[#2f6ff0]/20 hover:opacity-95 active:scale-[0.98]',
    accent:    'bg-[#22d3ee] hover:bg-[#06b6d4] text-zinc-950 font-bold shadow-lg shadow-cyan-500/20 active:scale-[0.98]',
    secondary: 'border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:border-hc-border dark:bg-hc-elevated dark:text-zinc-100 dark:hover:bg-[#1f2b42] dark:hover:border-cyan-500/40',
    danger:    'bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/20 hover:text-red-300',
    ghost:     'text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-hc-elevated dark:hover:text-zinc-100',
};

const sizeClass: Record<Size, string> = {
    sm:  'px-3 py-1.5 text-xs rounded-lg gap-1.5',
    md:  'px-4 py-2.5 text-sm rounded-xl gap-2',
    lg:  'px-5 py-3 text-sm rounded-xl gap-2',
};
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/40 disabled:pointer-events-none disabled:opacity-50"
        :class="[variantClass[variant], sizeClass[size]]"
    >
        <slot />
    </button>
</template>
