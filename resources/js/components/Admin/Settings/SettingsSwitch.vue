<script setup lang="ts">
/** The on/off switch used across the settings tabs. */
withDefaults(defineProps<{ label: string; onClass?: string }>(), { onClass: 'bg-blue-500' });

const enabled = defineModel<boolean>({ required: true });
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="enabled"
        :aria-label="label"
        class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
        :class="enabled ? onClass : 'bg-zinc-500'"
        @click="enabled = !enabled"
    >
        <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="enabled ? 'translate-x-4' : 'translate-x-0.5'" />
    </button>
</template>
