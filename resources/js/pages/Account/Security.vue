<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, KeyRound } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref } from 'vue';

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
}).withPrecognition('put', route('account.password.update'));

const reveal = ref(false);

/**
 * Length first, then variety — a long passphrase scores better here than a
 * short string with a symbol bolted on, which is the habit worth encouraging.
 */
const strength = computed(() => {
    const value = form.password;
    if (!value) return null;

    let score = 0;
    if (value.length >= 8) score++;
    if (value.length >= 12) score++;
    if (value.length >= 16) score++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^\w\s]/.test(value)) score++;

    const levels = [
        { min: 0, key: 'sec_pw_weak', bars: 1, bar: 'bg-red-500', text: dark.value ? 'text-red-400' : 'text-red-600' },
        { min: 3, key: 'sec_pw_fair', bars: 2, bar: 'bg-amber-500', text: dark.value ? 'text-amber-400' : 'text-amber-600' },
        { min: 4, key: 'sec_pw_good', bars: 3, bar: 'bg-lime-500', text: dark.value ? 'text-lime-400' : 'text-lime-600' },
        { min: 5, key: 'sec_pw_strong', bars: 4, bar: 'bg-emerald-500', text: dark.value ? 'text-emerald-400' : 'text-emerald-600' },
    ];

    return levels.filter((l) => score >= l.min).pop() ?? levels[0];
});

const mismatch = computed(
    () => form.password_confirmation.length > 0 && form.password !== form.password_confirmation,
);

const input = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
        : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
);
const label = computed(() =>
    dark.value
        ? 'text-zinc-400 text-[11px] font-bold uppercase tracking-widest'
        : 'text-zinc-500 text-[11px] font-bold uppercase tracking-widest',
);

function submit() {
    form.submit({
        onSuccess: () => {
            form.reset();
            reveal.value = false;
        },
    });
}
</script>

<template>
    <div
        class="rounded-2xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
    >
        <div
            class="px-6 py-4 border-b flex items-start gap-2.5"
            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'"
        >
            <KeyRound :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-blue-400' : 'text-blue-600'" />
            <div>
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.sec_pw_title') }}
                </h2>
                <p class="text-[12px] mt-0.5 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.sec_pw_subtitle') }}
                </p>
            </div>
        </div>

        <form class="p-6 flex flex-col gap-5" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label for="current_password" :class="label">{{ t('account.current_password') }}</label>
                <input
                    id="current_password"
                    v-model="form.current_password"
                    type="password"
                    autocomplete="current-password"
                    :aria-invalid="!!form.errors.current_password"
                    :aria-describedby="form.errors.current_password ? 'current_password_error' : undefined"
                    :class="[input, form.errors.current_password ? '!border-red-500/60' : '']"
                    @change="form.validate('current_password')"
                />
                <p v-if="form.errors.current_password" id="current_password_error" class="text-red-500 text-[12px] font-semibold">
                    {{ form.errors.current_password }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="new_password" :class="label">{{ t('account.new_password') }}</label>
                <div class="relative">
                    <input
                        id="new_password"
                        v-model="form.password"
                        :type="reveal ? 'text' : 'password'"
                        autocomplete="new-password"
                        :aria-invalid="!!form.errors.password"
                        :aria-describedby="form.errors.password ? 'new_password_error' : undefined"
                        :class="[input, 'pr-11', form.errors.password ? '!border-red-500/60' : '']"
                        @change="form.validate('password')"
                    />
                    <button
                        type="button"
                        :aria-label="reveal ? t('account.sec_pw_hide') : t('account.sec_pw_show')"
                        :aria-pressed="reveal"
                        class="absolute right-1.5 top-1/2 -translate-y-1/2 p-2 rounded-lg transition
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-700'"
                        @click="reveal = !reveal"
                    >
                        <component :is="reveal ? EyeOff : Eye" :size="15" :stroke-width="1.8" />
                    </button>
                </div>

                <div v-if="strength" class="flex items-center gap-2.5 pt-0.5">
                    <div class="flex gap-1 flex-1" aria-hidden="true">
                        <span
                            v-for="i in 4"
                            :key="i"
                            class="h-1 flex-1 rounded-full transition-colors"
                            :class="i <= strength.bars ? strength.bar : dark ? 'bg-zinc-800' : 'bg-zinc-200'"
                        />
                    </div>
                    <!-- Named, not just coloured: the bars alone would fail WCAG 1.4.1. -->
                    <span class="text-[11px] font-bold shrink-0" :class="strength.text">
                        {{ t('account.sec_pw_strength') }}: {{ t('account.' + strength.key) }}
                    </span>
                </div>

                <p v-if="form.errors.password" id="new_password_error" class="text-red-500 text-[12px] font-semibold">
                    {{ form.errors.password }}
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" :class="label">{{ t('account.confirm_new_password') }}</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    :type="reveal ? 'text' : 'password'"
                    autocomplete="new-password"
                    :aria-invalid="mismatch"
                    :aria-describedby="mismatch ? 'password_confirmation_error' : undefined"
                    :class="[input, mismatch ? '!border-red-500/60' : '']"
                />
                <p v-if="mismatch" id="password_confirmation_error" role="alert" class="text-red-500 text-[12px] font-semibold">
                    {{ t('account.sec_pw_mismatch') }}
                </p>
            </div>

            <div class="flex justify-end pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                <button
                    type="submit"
                    :disabled="form.processing || mismatch"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl
                           transition disabled:opacity-60 disabled:cursor-not-allowed shadow-md shadow-blue-500/20
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                >
                    {{ t('account.change_password') }}
                </button>
            </div>
        </form>
    </div>
</template>
