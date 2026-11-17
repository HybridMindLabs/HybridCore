<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Input from '@/components/UI/Input.vue';
import Button from '@/components/UI/Button.vue';
import PasswordField from '@/components/Auth/PasswordField.vue';
import OAuthButtons from '@/components/Auth/OAuthButtons.vue';
import CaptchaWidget from '@/components/UI/CaptchaWidget.vue';
import { useLocale } from '@/composables/useLocale';
import { ref } from 'vue';

const props = defineProps<{
    registrationEnabled: boolean;
    captcha: { provider: string; site_key: string | null };
}>();

const { t } = useLocale();

// Precognition: fields are validated live against the real server rules
// (unique email, password policy) as the user fills the form.
const form = useForm({ name: '', username: '', email: '', password: '', password_confirmation: '', captcha_token: '' })
    .withPrecognition('post', route('register'));

// The username follows the display name until the user edits it themselves.
const usernameTouched = ref(false);
function suggestUsername(name: string) {
    if (usernameTouched.value) return;
    form.username = name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 32);
}

function onCaptchaToken(token: string) {
    form.captcha_token = token;
}

function submit() {
    form.submit({ onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head :title="t('auth.register.title')" />
    <AuthCard
        v-if="!registrationEnabled"
        :title="t('auth.registration_closed')"
        :subtitle="t('auth.registration_closed_text')"
    >
        <Link :href="route('login')" class="text-[13px] font-semibold text-blue-500 transition-colors hover:text-blue-400">← {{ t('auth.back_to_sign_in') }}</Link>
    </AuthCard>

    <AuthCard v-else :title="t('auth.register.title')" :subtitle="t('auth.register.subtitle')">
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.register.name') }}</label>
                <Input id="name" v-model="form.name" type="text" autocomplete="nickname" maxlength="50" :error="form.errors.name" @change="form.validate('name')" @update:model-value="suggestUsername" />
            </div>
            <div>
                <label for="username" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.register.username') }}</label>
                <Input id="username" v-model="form.username" type="text" autocomplete="username" maxlength="32" :error="form.errors.username" @update:model-value="usernameTouched = true" @change="form.validate('username')" />
                <p v-if="!form.errors.username && form.username" class="mt-1.5 text-[12px] text-zinc-500">{{ t('auth.register.username_hint', { username: form.username.toLowerCase() }) }}</p>
            </div>
            <div>
                <label for="email" class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-500">{{ t('auth.register.email') }}</label>
                <Input id="email" v-model="form.email" type="email" placeholder="you@example.com" autocomplete="email" :error="form.errors.email" @change="form.validate('email')" />
            </div>
            <PasswordField
                id="password"
                v-model="form.password"
                :label="t('auth.register.password')"
                autocomplete="new-password"
                :error="form.errors.password"
                show-strength
                @change="form.validate('password')"
            />
            <PasswordField
                id="password_confirmation"
                v-model="form.password_confirmation"
                :label="t('auth.register.password_confirmation')"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                :must-match="form.password"
            />
            <CaptchaWidget :provider="props.captcha.provider" :site-key="props.captcha.site_key" @token="onCaptchaToken" />
            <p v-if="form.errors.captcha_token" role="alert" class="text-[12px] text-red-600 dark:text-red-400">{{ form.errors.captcha_token }}</p>
            <Button type="submit" size="lg" :disabled="form.processing" class="w-full justify-center">{{ t('auth.register.submit') }}</Button>
        </form>

        <OAuthButtons class="mt-6" />

        <template #footer>
            {{ t('auth.register.already_have_account') }}
            <Link :href="route('login')" class="font-bold text-blue-500 transition-colors hover:text-blue-400">{{ t('auth.sign_in') }}</Link>
        </template>
    </AuthCard>
</template>
