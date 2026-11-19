<script setup lang="ts">
import SettingsSwitch from './SettingsSwitch.vue';

/** A titled setting with a description on the left and a switch on the right. */
withDefaults(defineProps<{ title: string; hint?: string; onClass?: string; divided?: boolean }>(), {
    hint: undefined,
    onClass: 'bg-blue-500',
    divided: true,
});

const enabled = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="flex items-center justify-between py-3" :class="divided ? 'border-t border-zinc-800/70' : ''">
        <div>
            <p class="text-zinc-100 text-sm font-medium">{{ title }}</p>
            <p v-if="hint" class="text-zinc-600 text-xs mt-0.5">{{ hint }}</p>
        </div>
        <SettingsSwitch v-model="enabled" :label="title" :on-class="onClass" />
    </div>
</template>
