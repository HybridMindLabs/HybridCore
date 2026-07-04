<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Input from '@/components/UI/Input.vue';
import Button from '@/components/UI/Button.vue';
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
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.login.email') }}</label>
                <Input id="email" v-model="form.email" type="email" autocomplete="email" :error="form.errors.email" />
            </div>
            <div>
                <label for="password" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.new_password') }}</label>
                <Input id="password" v-model="form.password" type="password" autocomplete="new-password" :error="form.errors.password" />
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.register.password_confirmation') }}</label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
            </div>
            <Button type="submit" size="lg" :disabled="form.processing" class="w-full justify-center">{{ t('auth.reset.submit') }}</Button>
        </form>
    </AuthCard>
</template>
