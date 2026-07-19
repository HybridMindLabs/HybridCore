<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Input from '@/components/UI/Input.vue';
import Button from '@/components/UI/Button.vue';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

const { t } = useLocale();

const page = usePage<{ flash: { success: string | null } }>();
const status = computed(() => page.props.flash?.success);

const form = useForm({ email: '' });

function submit() {
    form.post(route('password.email'));
}
</script>

<template>
    <Head :title="t('auth.forgot.title')" />
    <AuthCard :title="t('auth.forgot.title')" :subtitle="t('auth.forgot.subtitle')">
        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-[13px] font-medium text-emerald-800 dark:text-emerald-400">
            {{ status }}
        </div>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.login.email') }}</label>
                <Input id="email" v-model="form.email" type="email" placeholder="you@example.com" autocomplete="email" :error="form.errors.email" />
            </div>
            <Button type="submit" size="lg" :disabled="form.processing" class="w-full justify-center">{{ t('auth.send_reset_link') }}</Button>
        </form>

        <template #footer>
            <Link :href="route('login')" class="font-semibold text-blue-500 transition-colors hover:text-blue-400">← {{ t('auth.back_to_sign_in') }}</Link>
        </template>
    </AuthCard>
</template>
