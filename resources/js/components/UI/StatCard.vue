<script setup lang="ts">
type Accent = 'default' | 'accent' | 'success' | 'warning' | 'danger';

withDefaults(defineProps<{
    label: string;
    value: string | number;
    description?: string;
    icon?: unknown;
    accent?: Accent;
}>(), { accent: 'default' });

const colors: Record<Accent, { border: string; icon: string; value: string; bg: string }> = {
    default: { border: 'border-l-[#334155]',   icon: 'text-zinc-500',  value: 'text-zinc-100', bg: 'bg-zinc-900/60' },
    accent:  { border: 'border-l-blue-500',   icon: 'text-blue-400',  value: 'text-blue-400', bg: 'bg-blue-500/10' },
    success: { border: 'border-l-[#22c55e]',   icon: 'text-emerald-400',  value: 'text-emerald-400', bg: 'bg-emerald-500/10' },
    warning: { border: 'border-l-[#f59e0b]',   icon: 'text-[#f59e0b]',  value: 'text-[#f59e0b]', bg: 'bg-[#f59e0b]/10' },
    danger:  { border: 'border-l-[#ef4444]',   icon: 'text-red-400',  value: 'text-red-400', bg: 'bg-red-500/10' },
};
</script>

<template>
    <div class="bg-[#111113] border border-zinc-800/70 border-l-2 rounded-xl px-4 py-3.5 flex items-center gap-3.5" :class="colors[accent].border">
        <div
            v-if="icon"
            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
            :class="colors[accent].bg"
        >
            <component :is="icon" :size="16" :stroke-width="1.75" :class="colors[accent].icon" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-zinc-500 text-xs font-medium">{{ label }}</p>
            <p class="text-xl font-bold tabular-nums leading-tight mt-0.5" :class="colors[accent].value">{{ value }}</p>
            <p v-if="description" class="text-zinc-600 text-xs mt-0.5 truncate">{{ description }}</p>
        </div>
    </div>
</template>
