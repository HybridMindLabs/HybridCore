<script setup lang="ts">
withDefaults(defineProps<{
    status?: 'online' | 'offline' | 'maintenance' | 'busy';
    pulse?: boolean;
    size?: 'sm' | 'md' | 'lg';
}>(), {
    status: 'online',
    pulse: true,
    size: 'md',
});

const colorMap = {
    online: 'bg-emerald-500',
    offline: 'bg-red-500',
    maintenance: 'bg-amber-500',
    busy: 'bg-cyan-500',
};

const pulseMap = {
    online: 'bg-emerald-400',
    offline: 'bg-red-400',
    maintenance: 'bg-amber-400',
    busy: 'bg-cyan-400',
};

const sizeMap = {
    sm: 'w-1.5 h-1.5',
    md: 'w-2 h-2',
    lg: 'w-2.5 h-2.5',
};
</script>

<template>
    <span class="relative inline-flex items-center justify-center shrink-0" :class="sizeMap[size]">
        <span
            v-if="pulse && status !== 'offline'"
            class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
            :class="pulseMap[status]"
        />
        <span
            class="relative inline-flex rounded-full"
            :class="[sizeMap[size], colorMap[status]]"
        />
    </span>
</template>
