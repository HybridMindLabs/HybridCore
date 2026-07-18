<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    modelValue: string;
    id: string;
    label: string;
    autocomplete: string;
    error?: string;
    /** Only worth showing where a password is being chosen, not typed back. */
    showStrength?: boolean;
    /** Compared against, to warn before the server does. */
    mustMatch?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useLocale();

const revealed = ref(false);
const fallbackId = useId();
const errorId = computed(() => `${props.id ?? fallbackId}-error`);
const strengthId = computed(() => `${props.id ?? fallbackId}-strength`);

/**
 * Length first, then variety — a long passphrase scores better here than a
 * short string with a symbol bolted on, which is the habit worth encouraging.
 * Same scale as the account security form, so the two never disagree.
 */
const strength = computed(() => {
    if (!props.showStrength || !props.modelValue) return null;

    const value = props.modelValue;
    let score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (value.length >= 16) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^\w\s]/.test(value)) score++;

    const levels = [
        { min: 0, key: 'auth.password_field.weak', bars: 1, bar: 'bg-red-500', text: 'text-red-600 dark:text-red-400' },
        { min: 3, key: 'auth.password_field.fair', bars: 2, bar: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-400' },
        { min: 4, key: 'auth.password_field.good', bars: 3, bar: 'bg-lime-500', text: 'text-lime-700 dark:text-lime-400' },
        { min: 5, key: 'auth.password_field.strong', bars: 4, bar: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-400' },
    ];

    return levels.filter((l) => score >= l.min).pop() ?? levels[0];
});

const mismatch = computed(
    () => props.mustMatch !== undefined && props.modelValue.length > 0 && props.modelValue !== props.mustMatch,
);

const describedBy = computed(() => {
    const ids = [];
    if (props.error || mismatch.value) ids.push(errorId.value);
    if (strength.value) ids.push(strengthId.value);

    return ids.length ? ids.join(' ') : undefined;
});
</script>

<template>
    <div>
        <label :for="id" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
            {{ label }}
        </label>

        <div class="relative">
            <input
                :id="id"
                :type="revealed ? 'text' : 'password'"
                :value="modelValue"
                :autocomplete="autocomplete"
                :aria-invalid="error || mismatch ? true : undefined"
                :aria-describedby="describedBy"
                class="w-full rounded-xl border px-4 py-3 pr-12 text-[14px] font-medium transition-colors focus:outline-none focus:ring-2
                       bg-white text-zinc-900 placeholder-zinc-400
                       dark:bg-zinc-900/60 dark:text-zinc-100 dark:placeholder-zinc-600"
                :class="error || mismatch
                    ? 'border-red-400/60 focus:border-red-400 focus:ring-red-400/15 dark:border-red-500/40'
                    : 'border-zinc-200 focus:border-blue-500/70 focus:ring-blue-500/15 dark:border-zinc-800 dark:focus:border-blue-500/50'"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />

            <!-- Typing a password blind is the commonest reason people fail to
                 sign in; every field here can be revealed. -->
            <button
                type="button"
                :aria-label="revealed ? t('auth.password_field.hide') : t('auth.password_field.show')"
                :aria-pressed="revealed"
                class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-lg p-2 transition
                       text-zinc-400 hover:text-zinc-700 dark:text-zinc-500 dark:hover:text-zinc-200
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                @click="revealed = !revealed"
            >
                <component :is="revealed ? EyeOff : Eye" :size="15" :stroke-width="1.8" />
            </button>
        </div>

        <div v-if="strength" :id="strengthId" class="mt-2 flex items-center gap-2.5">
            <div class="flex flex-1 gap-1" aria-hidden="true">
                <span
                    v-for="i in 4"
                    :key="i"
                    class="h-1 flex-1 rounded-full transition-colors"
                    :class="i <= strength.bars ? strength.bar : 'bg-zinc-200 dark:bg-zinc-800'"
                />
            </div>
            <!-- Named, not just coloured: the bars alone would fail WCAG 1.4.1. -->
            <span class="shrink-0 text-[11px] font-bold" :class="strength.text">{{ t(strength.key) }}</span>
        </div>

        <p v-if="error" :id="errorId" role="alert" class="mt-1 pl-1 text-xs font-semibold text-red-600 dark:text-red-400">
            {{ error }}
        </p>
        <p v-else-if="mismatch" :id="errorId" role="alert" class="mt-1 pl-1 text-xs font-semibold text-red-600 dark:text-red-400">
            {{ t('auth.password_field.mismatch') }}
        </p>
    </div>
</template>
