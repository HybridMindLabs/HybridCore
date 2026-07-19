<script setup lang="ts">
/**
 * A labelled input with room for a hint and an error.
 *
 * The hint is the point: every field in the installer explains what it wants
 * and where to find it, so nobody has to guess what "Host" means on their
 * particular hosting setup.
 */
withDefaults(defineProps<{
    label: string;
    modelValue: string | number;
    hint?: string;
    error?: string;
    type?: string;
    placeholder?: string;
    autocomplete?: string;
    optional?: boolean;
}>(), {
    type: 'text',
});

defineEmits<{ 'update:modelValue': [value: string | number] }>();
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between mb-1.5 gap-2">
            <label class="block text-zinc-400 text-xs font-semibold">{{ label }}</label>
            <span v-if="optional" class="text-zinc-500 text-[10px] font-medium uppercase tracking-wider">Optional</span>
        </div>

        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            class="w-full bg-zinc-900/60 border text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-500 focus:outline-none focus:ring-2 transition"
            :class="error
                ? 'border-red-500/50 focus:border-red-500/60 focus:ring-red-500/10'
                : 'border-zinc-800 focus:border-blue-500/50 focus:ring-blue-500/10'"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />

        <p v-if="error" class="text-red-400 text-xs mt-1.5 leading-relaxed">{{ error }}</p>
        <p v-else-if="hint" class="text-zinc-500 text-xs mt-1.5 leading-relaxed">{{ hint }}</p>
    </div>
</template>
