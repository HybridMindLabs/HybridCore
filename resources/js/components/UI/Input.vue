<script setup lang="ts">
defineProps<{
    modelValue?: string | number | null;
    type?: string;
    placeholder?: string;
    disabled?: boolean;
    error?: string;
    id?: string;
    autocomplete?: string;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div class="flex flex-col gap-1">
        <input
            :id="id"
            :type="type ?? 'text'"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            :autocomplete="autocomplete"
            class="w-full rounded-xl border px-4 py-3 text-[14px] font-medium transition-colors focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 bg-white text-zinc-900 placeholder-zinc-400 dark:bg-zinc-900/60 dark:text-zinc-100 dark:placeholder-zinc-600"
            :class="error
                ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/15 dark:border-red-500/40'
                : 'border-zinc-200 focus:border-blue-500/70 focus:ring-blue-500/15 dark:border-zinc-800 dark:focus:border-blue-500/50'"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <p v-if="error" class="pl-1 text-xs font-medium text-[#fb7185]">{{ error }}</p>
    </div>
</template>
