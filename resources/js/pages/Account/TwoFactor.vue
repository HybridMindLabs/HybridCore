<script setup lang="ts">
import { ref, computed } from 'vue';
import { Eye, EyeOff, Copy, ShieldCheck, ShieldOff, RefreshCw } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    enabled: boolean;
    recoveryCodes: string[] | null;
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

type Step = 'idle' | 'qr';
const step = ref<Step>('idle');
const qrUrl = ref('');
const secret = ref('');
const code = ref('');
const error = ref('');
const loading = ref(false);
const showCodes = ref(false);
const regenPassword = ref('');
const disablePassword = ref('');
const showRegenForm = ref(false);
const showDisableForm = ref(false);
const copied = ref('');

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function startSetup() {
    loading.value = true; error.value = '';
    try {
        const res = await fetch('/account/two-factor/setup', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (!res.ok) { error.value = data.message ?? 'Error'; return; }
        qrUrl.value = data.qr_url;
        secret.value = data.secret;
        step.value = 'qr';
    } catch { error.value = 'Network error'; }
    finally { loading.value = false; }
}

async function verifyAndEnable() {
    loading.value = true; error.value = '';
    try {
        const res = await fetch('/account/two-factor/confirm', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code.value }),
        });
        const data = await res.json();
        if (!res.ok) { error.value = data.message ?? 'Invalid code'; return; }
        window.location.reload();
    } catch { error.value = 'Network error'; }
    finally { loading.value = false; }
}

async function disable() {
    loading.value = true; error.value = '';
    try {
        const res = await fetch('/account/two-factor/disable', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: disablePassword.value }),
        });
        const data = await res.json();
        if (!res.ok) { error.value = data.message ?? 'Error'; return; }
        window.location.reload();
    } catch { error.value = 'Network error'; }
    finally { loading.value = false; }
}

async function regenerateCodes() {
    loading.value = true; error.value = '';
    try {
        const res = await fetch('/account/two-factor/recovery-codes', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: regenPassword.value }),
        });
        const data = await res.json();
        if (!res.ok) { error.value = data.message ?? 'Error'; return; }
        window.location.reload();
    } catch { error.value = 'Network error'; }
    finally { loading.value = false; }
}

function copyText(text: string, key: string) {
    navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => { copied.value = ''; }, 1500);
}

const input = computed(() => dark.value
    ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
    : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition'
);
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
        <div class="px-6 py-4 border-b flex items-center justify-between gap-3" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <div>
                <div class="flex items-center gap-2.5">
                    <p class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('account.2fa_title') }}</p>
                    <span
                        class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                        :class="enabled
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                            : dark ? 'border-zinc-800/70 bg-zinc-900/50 text-zinc-600' : 'border-zinc-200 bg-zinc-50 text-zinc-400'"
                    >
                        {{ enabled ? t('account.2fa_enabled') : t('account.2fa_disabled') }}
                    </span>
                </div>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                    {{ enabled ? t('account.2fa_enabled_hint') : t('account.2fa_disabled_hint') }}
                </p>
            </div>
            <component :is="enabled ? ShieldCheck : ShieldOff" :size="20" :stroke-width="1.6" :class="enabled ? 'text-emerald-400' : dark ? 'text-zinc-700' : 'text-zinc-300'" class="shrink-0" />
        </div>

        <div class="p-6 flex flex-col gap-6">
            <p class="text-[13px] leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.2fa_description') }}</p>

            <div v-if="error" class="px-4 py-3 rounded-xl border border-red-500/20 bg-red-500/10 text-red-400 text-[13px] font-semibold">{{ error }}</div>

            <template v-if="!enabled">
                <template v-if="step === 'idle'">
                    <button
                        type="button"
                        :disabled="loading"
                        class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl transition disabled:opacity-60 shadow-md shadow-blue-500/20 self-start"
                        @click="startSetup"
                    >
                        <ShieldCheck :size="14" :stroke-width="2" />
                        {{ t('account.2fa_setup') }}
                    </button>
                </template>

                <template v-else-if="step === 'qr'">
                    <div class="flex flex-col gap-3">
                        <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_step1') }}</p>
                        <p class="text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.2fa_step1_hint') }}</p>
                        <div class="p-3 rounded-2xl self-start" :class="dark ? 'bg-white' : 'bg-zinc-50 border border-zinc-200'">
                            <img :src="qrUrl" alt="QR Code" class="w-40 h-40 block" />
                        </div>
                        <div>
                            <p class="text-[12px] mb-1.5" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('account.2fa_manual_key') }}</p>
                            <div class="flex items-center gap-2">
                                <code class="font-mono text-[13px] px-3 py-2 rounded-lg border flex-1" :class="dark ? 'bg-zinc-900/60 border-zinc-800 text-zinc-100' : 'bg-zinc-50 border-zinc-200 text-zinc-800'">{{ secret }}</code>
                                <button type="button" class="p-2 rounded-lg border transition" :class="dark ? 'border-zinc-800 hover:bg-zinc-800 text-zinc-500' : 'border-zinc-200 hover:bg-zinc-50 text-zinc-400'" @click="copyText(secret, 'secret')">
                                    <Copy :size="14" :stroke-width="1.8" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_step2') }}</p>
                        <input
                            v-model="code"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="000000"
                            :class="[input, 'font-mono text-center text-[20px] tracking-[0.3em]']"
                            @keyup.enter="verifyAndEnable"
                        />
                        <div class="flex items-center gap-3">
                            <button type="button" :disabled="loading || code.length < 6" class="bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl transition disabled:opacity-60 shadow-md shadow-blue-500/20" @click="verifyAndEnable">
                                {{ t('account.2fa_verify') }}
                            </button>
                            <button type="button" class="text-[13px] font-semibold transition" :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-800'" @click="step = 'idle'">
                                {{ t('account.2fa_cancel') }}
                            </button>
                        </div>
                    </div>
                </template>
            </template>

            <template v-else>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_recovery_title') }}</p>
                            <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.2fa_recovery_hint') }}</p>
                        </div>
                        <button type="button" class="p-2 rounded-lg border transition shrink-0" :class="dark ? 'border-zinc-800 hover:bg-zinc-800 text-zinc-500' : 'border-zinc-200 hover:bg-zinc-50 text-zinc-400'" @click="showCodes = !showCodes">
                            <component :is="showCodes ? EyeOff : Eye" :size="14" :stroke-width="1.8" />
                        </button>
                    </div>

                    <div v-if="recoveryCodes && recoveryCodes.length">
                        <div v-if="showCodes" class="grid grid-cols-2 gap-2">
                            <div
                                v-for="rc in recoveryCodes"
                                :key="rc"
                                class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-200 bg-zinc-50'"
                            >
                                <code class="font-mono text-[12px]" :class="dark ? 'text-zinc-200' : 'text-zinc-700'">{{ rc }}</code>
                                <button type="button" @click="copyText(rc, rc)" :class="dark ? 'text-zinc-600 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-700'" class="transition">
                                    <Copy :size="12" :stroke-width="1.8" />
                                </button>
                            </div>
                        </div>
                        <p v-else class="text-[13px] italic" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('account.2fa_recovery_reveal') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                    <div class="flex items-center justify-between">
                        <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_regenerate') }}</p>
                        <button type="button" class="flex items-center gap-1.5 text-[12px] font-semibold rounded-lg border px-3 py-2 transition" :class="dark ? 'border-zinc-800 text-zinc-500 hover:text-zinc-200 hover:bg-zinc-800' : 'border-zinc-200 text-zinc-400 hover:text-zinc-800 hover:bg-zinc-50'" @click="showRegenForm = !showRegenForm">
                            <RefreshCw :size="12" :stroke-width="1.8" />
                            {{ t('account.2fa_regenerate') }}
                        </button>
                    </div>
                    <div v-if="showRegenForm" class="flex gap-2">
                        <input v-model="regenPassword" type="password" :placeholder="t('account.2fa_confirm_pw')" :class="input" @keyup.enter="regenerateCodes" />
                        <button type="button" :disabled="loading || !regenPassword" class="bg-blue-500 hover:bg-blue-600 text-white font-bold text-[13px] px-4 py-2.5 rounded-xl transition disabled:opacity-60 shadow-md shadow-blue-500/20 shrink-0" @click="regenerateCodes">
                            {{ t('account.2fa_regenerate') }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                    <div>
                        <p class="text-[14px] font-bold text-red-400">{{ t('account.2fa_disable_title') }}</p>
                        <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.2fa_disable_hint') }}</p>
                    </div>
                    <button type="button" class="self-start text-[13px] font-semibold text-red-400 hover:text-red-300 transition" @click="showDisableForm = !showDisableForm">
                        {{ t('account.2fa_disable_btn') }}
                    </button>
                    <div v-if="showDisableForm" class="flex gap-2">
                        <input v-model="disablePassword" type="password" :placeholder="t('account.2fa_confirm_your_pw')" :class="input" @keyup.enter="disable" />
                        <button type="button" :disabled="loading || !disablePassword" class="bg-red-500/10 border border-red-500/30 text-red-400 font-bold text-[12px] px-4 py-2.5 rounded-xl hover:bg-red-500/20 transition disabled:opacity-60 shrink-0" @click="disable">
                            {{ t('account.2fa_disable_btn') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>