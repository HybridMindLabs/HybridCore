<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Input from '@/components/UI/Input.vue';
import Button from '@/components/UI/Button.vue';
import { useLocale } from '@/composables/useLocale';

const { t } = useLocale();

const form = useForm({ email: '' });

function submit() {
    form.post(route('oauth.complete-profile.store'));
}
</script>

<template>
    <Head :title="t('auth.complete_profile_title')" />
    <AuthCard :title="t('auth.complete_profile_title')" :subtitle="t('auth.complete_profile_subtitle')">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.login.email') }}</label>
                <Input id="email" v-model="form.email" type="email" placeholder="you@example.com" autocomplete="email" :error="form.errors.email" />
            </div>
            <Button type="submit" size="lg" :disabled="form.processing" class="w-full justify-center">{{ t('auth.complete_profile_submit') }}</Button>
        </form>
    </AuthCard>
</template>
