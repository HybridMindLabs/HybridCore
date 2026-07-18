<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Button from '@/components/UI/Button.vue';
import { useLocale } from '@/composables/useLocale';
import { useTheme } from '@/composables/useTheme';

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

/**
 * This screen was the one auth page not built on AuthCard: its own standalone
 * layout, outside PublicLayout, and the only one still in English. Anyone with
 * two-factor on met it at every sign-in.
 */
const useRecovery = ref(false);
const form = useForm({ code: '' });

const subtitle = computed(() =>
    useRecovery.value ? t('auth.two_factor.subtitle_recovery') : t('auth.two_factor.subtitle_app'),
);
const label = computed(() =>
    useRecovery.value ? t('auth.two_factor.label_recovery') : t('auth.two_factor.label_app'),
);

function toggleMode() {
    useRecovery.value = !useRecovery.value;
    form.reset('code');
    form.clearErrors();
}

function submit() {
    form.post(route('auth.2fa.verify'), {
        // A recovery code is single-use; leaving it in the box after a failure
        // invites retrying one that is already spent.
        onFinish: () => form.reset('code'),
    });
}
</script>

<template>
    <Head :title="t('auth.two_factor.title')" />

    <AuthCard
        :title="t('auth.two_factor.title')"
        :subtitle="subtitle"
        :shell-title="t('auth.two_factor.shell_title')"
        :shell-subtitle="t('auth.two_factor.shell_subtitle')"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="code" class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest"
                    :class="dark ? 'text-zinc-400' : 'text-zinc-500'">
                    <component :is="useRecovery ? KeyRound : ShieldCheck" :size="12" :stroke-width="2.2" aria-hidden="true" />
                    {{ label }}
                </label>

                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    :inputmode="useRecovery ? 'text' : 'numeric'"
                    :maxlength="useRecovery ? 21 : 6"
                    :placeholder="useRecovery ? 'xxxxxxxxxx-xxxxxxxxxx' : '000000'"
                    autofocus
                    autocomplete="one-time-code"
                    :aria-invalid="!!form.errors.code"
                    :aria-describedby="form.errors.code ? 'code_error' : undefined"
                    class="w-full rounded-xl border px-4 py-3 text-[15px] font-mono text-center tracking-[0.25em]
                           placeholder:tracking-normal transition focus:outline-none focus:ring-2 focus:ring-blue-500/10"
                    :class="[
                        dark
                            ? 'border-zinc-800 bg-zinc-900/60 text-zinc-100 placeholder:text-zinc-600 focus:border-blue-500/50'
                            : 'border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 focus:border-blue-400',
                        form.errors.code ? '!border-red-500/60' : '',
                    ]"
                />

                <p v-if="form.errors.code" id="code_error" role="alert"
                    class="mt-1.5 text-center text-[12px] font-semibold text-red-600 dark:text-red-400">
                    {{ form.errors.code }}
                </p>
            </div>

            <Button type="submit" size="lg" :disabled="form.processing || !form.code.length" class="w-full justify-center">
                {{ form.processing ? t('auth.two_factor.verifying') : t('auth.two_factor.submit') }}
            </Button>
        </form>

        <button
            type="button"
            class="mt-4 w-full text-center text-[12px] font-semibold transition rounded
                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
            :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
            @click="toggleMode"
        >
            {{ useRecovery ? t('auth.two_factor.use_app') : t('auth.two_factor.use_recovery') }}
        </button>

        <template #footer>
            {{ t('auth.two_factor.lost_access') }}
        </template>
    </AuthCard>
</template>
