<script setup lang="ts">
type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';
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
    primary:   'bg-gradient-to-br from-[#4f8fff] to-[#2f6ff0] text-white font-bold shadow-lg shadow-[#2f6ff0]/20 hover:opacity-95',
    secondary: 'border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:border-white/10 dark:bg-[#111a2a] dark:text-zinc-100 dark:hover:bg-[#18243a] dark:hover:border-[#3b82f6]/30',
    danger:    'bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500/15',
    ghost:     'text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-zinc-400 dark:hover:bg-[#111a2a] dark:hover:text-zinc-100',
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
        class="inline-flex items-center justify-center font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3b82f6]/45 disabled:pointer-events-none disabled:opacity-50"
        :class="[variantClass[variant], sizeClass[size]]"
    >
        <slot />
    </button>
</template>
