<script setup lang="ts">
import { ref, computed } from 'vue';
import { Check, Copy, Download, Eye, EyeOff, RefreshCw, ShieldCheck, ShieldOff } from '@lucide/vue';
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
const qrSvg = ref('');
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

/**
 * Wraps the four endpoints. Every call used to hand its response straight to
 * res.json(); anything that is not JSON — a 419 after the session expires, a
 * 429 from the throttle, an HTML error page — threw and surfaced as "Network
 * error", which pointed the user at their connection instead of the real cause.
 */
async function send(url: string, method: string, body?: Record<string, unknown>) {
    loading.value = true;
    error.value = '';

    try {
        const res = await fetch(url, {
            method,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });

        const data = await res.json().catch(() => null);

        if (!res.ok) {
            error.value = data?.message ?? t('account.2fa_network_error');
            return null;
        }

        return data ?? {};
    } catch {
        error.value = t('account.2fa_network_error');
        return null;
    } finally {
        loading.value = false;
    }
}

async function startSetup() {
    const data = await send(route('account.2fa.setup'), 'POST');
    if (!data) return;

    qrSvg.value = data.qr_svg;
    secret.value = data.secret;
    step.value = 'qr';
}

async function verifyAndEnable() {
    if (await send(route('account.2fa.confirm'), 'POST', { code: code.value })) {
        window.location.reload();
    }
}

async function disable() {
    if (await send(route('account.2fa.disable'), 'DELETE', { password: disablePassword.value })) {
        window.location.reload();
    }
}

async function regenerateCodes() {
    if (!window.confirm(t('account.2fa_regen_confirm'))) return;

    if (await send(route('account.2fa.recovery-codes'), 'POST', { password: regenPassword.value })) {
        window.location.reload();
    }
}

function copyText(text: string, key: string) {
    navigator.clipboard.writeText(text);
    copied.value = key;
    setTimeout(() => {
        copied.value = '';
    }, 1500);
}

/** Recovery codes are only useful off the device, so make saving them one click. */
function downloadCodes() {
    if (!props.recoveryCodes?.length) return;

    const blob = new Blob([props.recoveryCodes.join('\n') + '\n'], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'recovery-codes.txt';
    link.click();
    URL.revokeObjectURL(url);
}

const input = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-500 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
        : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
);
const ghostBtn = computed(() =>
    dark.value
        ? 'border-zinc-800 hover:bg-zinc-800 text-zinc-400'
        : 'border-zinc-200 hover:bg-zinc-50 text-zinc-500',
);
</script>

<template>
    <div
        class="rounded-2xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
    >
        <div
            class="px-6 py-4 border-b flex items-center justify-between gap-3"
            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'"
        >
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                        {{ t('account.2fa_title') }}
                    </h2>
                    <!-- Icon + word, not colour alone (WCAG 1.4.1). -->
                    <span
                        class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border"
                        :class="enabled
                            ? dark ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400' : 'border-emerald-600/30 bg-emerald-50 text-emerald-800'
                            : dark ? 'border-zinc-800/70 bg-zinc-900/50 text-zinc-400' : 'border-zinc-200 bg-zinc-50 text-zinc-500'"
                    >
                        <component :is="enabled ? ShieldCheck : ShieldOff" :size="10" :stroke-width="2.4" />
                        {{ enabled ? t('account.2fa_enabled') : t('account.2fa_disabled') }}
                    </span>
                </div>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ enabled ? t('account.2fa_enabled_hint') : t('account.2fa_disabled_hint') }}
                </p>
            </div>
            <component
                :is="enabled ? ShieldCheck : ShieldOff"
                :size="20"
                :stroke-width="1.6"
                aria-hidden="true"
                class="shrink-0"
                :class="enabled ? 'text-emerald-500' : dark ? 'text-zinc-500' : 'text-zinc-300'"
            />
        </div>

        <div class="p-6 flex flex-col gap-6">
            <p class="text-[13px] leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ t('account.2fa_description') }}
            </p>

            <p
                v-if="error"
                role="alert"
                class="px-4 py-3 rounded-xl border text-[13px] font-semibold"
                :class="dark ? 'border-red-500/20 bg-red-500/10 text-red-400' : 'border-red-200 bg-red-50 text-red-700'"
            >
                {{ error }}
            </p>

            <template v-if="!enabled">
                <button
                    v-if="step === 'idle'"
                    type="button"
                    :disabled="loading"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px]
                           px-5 py-2.5 rounded-xl transition disabled:opacity-60 shadow-md shadow-blue-500/20 self-start
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                    @click="startSetup"
                >
                    <ShieldCheck :size="14" :stroke-width="2" />
                    {{ t('account.2fa_setup') }}
                </button>

                <template v-else>
                    <div class="flex flex-col gap-3">
                        <p class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_step1') }}</p>
                        <p class="text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.2fa_step1_hint') }}</p>

                        <!-- Always on white: the code carries its own quiet zone
                             but still needs a light backing to scan reliably. -->
                        <div class="p-3 rounded-2xl self-start bg-white border" :class="dark ? 'border-zinc-800' : 'border-zinc-200'">
                            <img :src="qrSvg" :alt="t('account.2fa_qr_alt')" width="160" height="160" class="w-40 h-40 block" />
                        </div>

                        <div>
                            <p class="text-[12px] mb-1.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.2fa_manual_key') }}</p>
                            <div class="flex items-center gap-2">
                                <code
                                    class="font-mono text-[13px] px-3 py-2 rounded-lg border flex-1 break-all"
                                    :class="dark ? 'bg-zinc-900/60 border-zinc-800 text-zinc-100' : 'bg-zinc-50 border-zinc-200 text-zinc-800'"
                                >{{ secret }}</code>
                                <button
                                    type="button"
                                    :aria-label="t('account.2fa_manual_key')"
                                    class="p-2 rounded-lg border transition shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                    :class="ghostBtn"
                                    @click="copyText(secret, 'secret')"
                                >
                                    <component :is="copied === 'secret' ? Check : Copy" :size="14" :stroke-width="1.8" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <label for="totp_code" class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                            {{ t('account.2fa_step2') }}
                        </label>
                        <input
                            id="totp_code"
                            v-model="code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            placeholder="000000"
                            :class="[input, 'font-mono text-center text-[20px] tracking-[0.3em]']"
                            @keyup.enter="verifyAndEnable"
                        />
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                :disabled="loading || code.length < 6"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] px-5 py-2.5 rounded-xl
                                       transition disabled:opacity-60 disabled:cursor-not-allowed shadow-md shadow-blue-500/20
                                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                @click="verifyAndEnable"
                            >
                                {{ t('account.2fa_verify') }}
                            </button>
                            <button
                                type="button"
                                class="text-[13px] font-semibold transition"
                                :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'"
                                @click="step = 'idle'; error = ''"
                            >
                                {{ t('account.2fa_cancel') }}
                            </button>
                        </div>
                    </div>
                </template>
            </template>

            <template v-else>
                <div class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_recovery_title') }}</h3>
                            <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.2fa_recovery_hint') }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                v-if="recoveryCodes && recoveryCodes.length"
                                type="button"
                                :aria-label="t('account.2fa_recovery_download')"
                                class="p-2 rounded-lg border transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                :class="ghostBtn"
                                @click="downloadCodes"
                            >
                                <Download :size="14" :stroke-width="1.8" />
                            </button>
                            <button
                                type="button"
                                :aria-label="showCodes ? t('account.sec_pw_hide') : t('account.sec_pw_show')"
                                :aria-pressed="showCodes"
                                class="p-2 rounded-lg border transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                                :class="ghostBtn"
                                @click="showCodes = !showCodes"
                            >
                                <component :is="showCodes ? EyeOff : Eye" :size="14" :stroke-width="1.8" />
                            </button>
                        </div>
                    </div>

                    <div v-if="recoveryCodes && recoveryCodes.length">
                        <div v-if="showCodes" class="flex flex-col gap-2">
                            <p
                                class="text-[12px] rounded-lg border px-3 py-2 leading-relaxed"
                                :class="dark ? 'border-amber-500/20 bg-amber-500/10 text-amber-300' : 'border-amber-300 bg-amber-50 text-amber-800'"
                            >
                                {{ t('account.2fa_recovery_warning') }}
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div
                                    v-for="rc in recoveryCodes"
                                    :key="rc"
                                    class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                    :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-200 bg-zinc-50'"
                                >
                                    <code class="font-mono text-[12px] break-all" :class="dark ? 'text-zinc-200' : 'text-zinc-500'">{{ rc }}</code>
                                    <button
                                        type="button"
                                        :aria-label="t('account.2fa_copied')"
                                        class="transition shrink-0"
                                        :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-500'"
                                        @click="copyText(rc, rc)"
                                    >
                                        <component :is="copied === rc ? Check : Copy" :size="12" :stroke-width="1.8" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-[13px] italic" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                            {{ t('account.2fa_recovery_reveal') }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-[14px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.2fa_regenerate') }}</h3>
                        <button
                            type="button"
                            :aria-expanded="showRegenForm"
                            class="flex items-center gap-1.5 text-[12px] font-semibold rounded-lg border px-3 py-2 transition shrink-0
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                            :class="ghostBtn"
                            @click="showRegenForm = !showRegenForm"
                        >
                            <RefreshCw :size="12" :stroke-width="1.8" />
                            {{ t('account.2fa_regenerate') }}
                        </button>
                    </div>
                    <div v-if="showRegenForm" class="flex gap-2">
                        <label for="regen_password" class="sr-only">{{ t('account.2fa_confirm_pw') }}</label>
                        <input
                            id="regen_password"
                            v-model="regenPassword"
                            type="password"
                            autocomplete="current-password"
                            :placeholder="t('account.2fa_confirm_pw')"
                            :class="input"
                            @keyup.enter="regenerateCodes"
                        />
                        <button
                            type="button"
                            :disabled="loading || !regenPassword"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-[13px] px-4 py-2.5 rounded-xl
                                   transition disabled:opacity-60 disabled:cursor-not-allowed shadow-md shadow-blue-500/20 shrink-0
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                            @click="regenerateCodes"
                        >
                            {{ t('account.2fa_regenerate') }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                    <div>
                        <h3 class="text-[14px] font-bold" :class="dark ? 'text-red-400' : 'text-red-600'">{{ t('account.2fa_disable_title') }}</h3>
                        <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.2fa_disable_hint') }}</p>
                    </div>
                    <button
                        type="button"
                        :aria-expanded="showDisableForm"
                        class="self-start text-[13px] font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                        :class="dark ? 'text-red-400 hover:text-red-300' : 'text-red-600 hover:text-red-700'"
                        @click="showDisableForm = !showDisableForm"
                    >
                        {{ t('account.2fa_disable_btn') }}
                    </button>
                    <div v-if="showDisableForm" class="flex gap-2">
                        <label for="disable_password" class="sr-only">{{ t('account.2fa_confirm_your_pw') }}</label>
                        <input
                            id="disable_password"
                            v-model="disablePassword"
                            type="password"
                            autocomplete="current-password"
                            :placeholder="t('account.2fa_confirm_your_pw')"
                            :class="input"
                            @keyup.enter="disable"
                        />
                        <button
                            type="button"
                            :disabled="loading || !disablePassword"
                            class="border font-bold text-[12px] px-4 py-2.5 rounded-xl transition disabled:opacity-60
                                   disabled:cursor-not-allowed shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                            :class="dark
                                ? 'bg-red-500/10 border-red-500/30 text-red-400 hover:bg-red-500/20'
                                : 'bg-red-50 border-red-300 text-red-700 hover:bg-red-100'"
                            @click="disable"
                        >
                            {{ t('account.2fa_disable_btn') }}
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
