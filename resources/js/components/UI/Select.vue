<script setup lang="ts">
defineProps<{
    modelValue?: string | number | null;
    options: { value: string | number; label: string }[];
    placeholder?: string;
    disabled?: boolean;
    error?: string;
    id?: string;
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <div class="flex flex-col gap-1">
        <select
            :id="id"
            :value="modelValue ?? ''"
            :disabled="disabled"
            class="w-full bg-[#09090b] border rounded-lg px-3 py-2 text-sm text-zinc-100 transition-colors focus:outline-none focus:ring-1 disabled:opacity-50 disabled:cursor-not-allowed appearance-none cursor-pointer"
            :class="error
                ? 'border-red-500/40 focus:border-red-500 focus:ring-red-500/20'
                : 'border-zinc-800/70 focus:border-blue-500/50 focus:ring-blue-500/10'"
            @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option
                v-for="opt in options"
                :key="opt.value"
                :value="opt.value"
                class="bg-[#111113]"
            >
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="text-xs text-red-400">{{ error }}</p>
    </div>
</template>
