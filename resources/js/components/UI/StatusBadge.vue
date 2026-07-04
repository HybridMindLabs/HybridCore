<script setup lang="ts">
type Status = 'online' | 'offline' | 'unknown' | 'maintenance';

withDefaults(defineProps<{ status: Status; showDot?: boolean }>(), { showDot: true });

const cfg: Record<Status, { label: string; classes: string; dot: string }> = {
    online:      { label: 'Online',      classes: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',   dot: 'bg-[#22c55e]' },
    offline:     { label: 'Offline',     classes: 'bg-red-500/10 text-red-400 border-red-500/20',   dot: 'bg-red-500' },
    unknown:     { label: 'Unknown',     classes: 'bg-[#475569]/20 text-zinc-400 border-[#475569]/20',   dot: 'bg-[#475569]' },
    maintenance: { label: 'Maintenance', classes: 'bg-[#f59e0b]/10 text-[#f59e0b] border-[#f59e0b]/20',   dot: 'bg-[#f59e0b]' },
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full border"
        :class="cfg[status].classes"
    >
        <span
            v-if="showDot"
            class="w-1.5 h-1.5 rounded-full shrink-0"
            :class="[cfg[status].dot, status === 'online' ? 'hc-live-dot' : '']"
        />
        {{ cfg[status].label }}
    </span>
</template>
