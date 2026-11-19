<script setup lang="ts">
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps<{ data: Record<string, any> }>();

const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');
const space = computed(() => ({ sm: 'py-2', md: 'py-6', lg: 'py-12' }[props.data.size as string] ?? 'py-6'));
</script>

<template>
    <div :class="space" aria-hidden="true">
        <hr v-if="data.style !== 'space'" class="border-0 h-px" :class="dark ? 'bg-white/[0.08]' : 'bg-zinc-200'" />
    </div>
</template>
