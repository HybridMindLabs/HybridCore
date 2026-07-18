<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Input from '@/components/UI/Input.vue';
import Button from '@/components/UI/Button.vue';
import PasswordField from '@/components/Auth/PasswordField.vue';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{ token: string; email: string }>();

const { t } = useLocale();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.update'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head :title="t('auth.reset.title')" />
    <AuthCard :title="t('auth.reset.title')" :subtitle="t('auth.reset.subtitle')">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ t('auth.login.email') }}</label>
                <!-- The address is fixed by the signed link; editing it only
                     produced a failed reset. -->
                <Input id="email" v-model="form.email" type="email" autocomplete="email" readonly :error="form.errors.email" />
                <p class="mt-1 pl-1 text-xs text-zinc-500 dark:text-zinc-400">{{ t('auth.reset_email_locked') }}</p>
            </div>
            <PasswordField
                id="password"
                v-model="form.password"
                :label="t('auth.new_password')"
                autocomplete="new-password"
                :error="form.errors.password"
                show-strength
            />
            <PasswordField
                id="password_confirmation"
                v-model="form.password_confirmation"
                :label="t('auth.register.password_confirmation')"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                :must-match="form.password"
            />
            <Button type="submit" size="lg" :disabled="form.processing" class="w-full justify-center">{{ t('auth.reset.submit') }}</Button>
        </form>
    </AuthCard>
</template>
