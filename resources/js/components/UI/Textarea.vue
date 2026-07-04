<script setup lang="ts">
defineProps<{
    modelValue?: string | null;
    placeholder?: string;
    rows?: number;
    disabled?: boolean;
    error?: string;
    id?: string;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div class="flex flex-col gap-1">
        <textarea
            :id="id"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :rows="rows ?? 3"
            :disabled="disabled"
            class="w-full bg-[#09090b] border rounded-lg px-3 py-2 text-sm text-zinc-100 placeholder-[#475569] resize-none transition-colors focus:outline-none focus:ring-1 disabled:opacity-50 disabled:cursor-not-allowed"
            :class="error
                ? 'border-red-500/40 focus:border-red-500 focus:ring-red-500/20'
                : 'border-zinc-800/70 focus:border-blue-500/50 focus:ring-blue-500/10'"
            @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
    </div>
</template>
