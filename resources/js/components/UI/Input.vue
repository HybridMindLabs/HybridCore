<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{
    modelValue?: string | number | null;
    type?: string;
    placeholder?: string;
    disabled?: boolean;
    readonly?: boolean;
    error?: string;
    id?: string;
    autocomplete?: string;
    inputmode?: 'text' | 'numeric' | 'email' | 'tel' | 'url' | 'search';
}>();

defineEmits<{ 'update:modelValue': [value: string] }>();

/**
 * The error text was rendered as a loose paragraph, so a screen reader had no
 * way to connect it to the field it belongs to and never announced it when it
 * appeared. It gets an id, and the input points at it.
 */
const fallbackId = useId();
const errorId = computed(() => `${props.id ?? fallbackId}-error`);
</script>

<template>
    <div class="flex flex-col gap-1">
        <input
            :id="id"
            :type="type ?? 'text'"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="error ? errorId : undefined"
            class="w-full rounded-xl border px-4 py-3 text-[14px] font-medium transition-colors focus:outline-none focus:ring-2
                   disabled:cursor-not-allowed disabled:opacity-50 read-only:cursor-default
                   bg-white text-zinc-900 placeholder-zinc-400
                   dark:bg-zinc-900/60 dark:text-zinc-100 dark:placeholder-zinc-600"
            :class="error
                ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/15 dark:border-red-500/40'
                : 'border-zinc-200 focus:border-blue-500/70 focus:ring-blue-500/15 dark:border-zinc-800 dark:focus:border-blue-500/50'"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />

        <!--
            Two reds, because no single one clears 4.5:1 on both surfaces. The
            previous #fb7185 measured 2.69:1 against the white card — error text
            nobody could comfortably read in the light theme. red-600 gives
            4.83:1 there, red-400 gives 6.82:1 on the dark card.
        -->
        <p v-if="error" :id="errorId" role="alert" class="pl-1 text-xs font-semibold text-red-600 dark:text-red-400">
            {{ error }}
        </p>
    </div>
</template>
