<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import AuthCard from '@/components/Auth/AuthCard.vue';
import Button from '@/components/UI/Button.vue';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

const { t } = useLocale();

const page = usePage<{ flash: { success: string | null } }>();
const status = computed(() => page.props.flash?.success);

const form = useForm({});

function resend() {
    form.post(route('verification.send'));
}
</script>

<template>
    <Head :title="t('auth.verify.title')" />
    <AuthCard :title="t('auth.verify.title')" :subtitle="t('auth.verify.subtitle')">
        <div class="flex justify-center mb-5">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-blue-500/25 bg-blue-500/10 dark:border-blue-500/20 dark:bg-blue-500/10">
                <MailCheck :size="28" :stroke-width="1.5" class="text-blue-500" />
            </div>
        </div>

        <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-[13px] font-medium text-emerald-600 dark:text-emerald-400">
            {{ status }}
        </div>

        <p class="mb-5 text-center text-[13px] leading-relaxed text-zinc-500 dark:text-zinc-400">{{ t('auth.verify_hint') }}</p>

        <Button type="button" size="lg" :disabled="form.processing" class="w-full justify-center" @click="resend">
            {{ t('auth.resend_verification') }}
        </Button>
    </AuthCard>
</template>
