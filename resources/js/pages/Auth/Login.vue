<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Input from '@/components/UI/Input.vue';
import Button from '@/components/UI/Button.vue';
import PasswordField from '@/components/Auth/PasswordField.vue';
import OAuthButtons from '@/components/Auth/OAuthButtons.vue';
import { useLocale } from '@/composables/useLocale';

const { t } = useLocale();

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post(route('login.store'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t('auth.login.title')" />
    <AuthCard :title="t('auth.login.title')" :subtitle="t('auth.login.subtitle')">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.login.email') }}</label>
                <Input id="email" v-model="form.email" type="email" placeholder="you@example.com" autocomplete="email" :error="form.errors.email" />
            </div>
            <div>
                <div class="mb-2 flex items-center justify-end">
                    <Link :href="route('password.request')" class="text-[12px] font-semibold text-blue-600 transition-colors hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">{{ t('auth.login.forgot_password') }}</Link>
                </div>
                <PasswordField
                    id="password"
                    v-model="form.password"
                    :label="t('auth.login.password')"
                    autocomplete="current-password"
                    :error="form.errors.password"
                />
            </div>
            <label class="flex cursor-pointer select-none items-center gap-2.5">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border border-zinc-300 bg-white accent-blue-500 dark:border-zinc-700 dark:bg-zinc-900" />
                <span class="text-[13px] font-medium text-zinc-500 dark:text-zinc-400">{{ t('auth.login.remember') }}</span>
            </label>
            <Button type="submit" size="lg" :disabled="form.processing" class="w-full justify-center">{{ t('auth.login.submit') }}</Button>
        </form>

        <OAuthButtons class="mt-6" />

        <template #footer>
            {{ t('auth.no_account') }}
            <Link :href="route('register')" class="font-bold text-blue-500 transition-colors hover:text-blue-400">{{ t('auth.login.create_account') }}</Link>
        </template>
    </AuthCard>
</template>
