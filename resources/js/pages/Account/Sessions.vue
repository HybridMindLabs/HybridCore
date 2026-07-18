<script setup lang="ts">
import { ref, computed } from 'vue';
import { Info, Monitor, Smartphone } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface SessionItem {
    id: string;
    ip_address: string | null;
    last_activity: number;
    is_current: boolean;
    device: { browser: string; os: string; mobile: boolean };
}

const props = defineProps<{ sessions: SessionItem[] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const password = ref('');
const showConfirm = ref(false);
const loading = ref(false);
const error = ref('');
const revoking = ref<string | null>(null);

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function formatTime(ts: number): string {
    const diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60) return t('account.sessions_just_now');
    if (diff < 3600) return t('account.sessions_minutes_ago', { n: Math.floor(diff / 60) });
    if (diff < 86400) return t('account.sessions_hours_ago', { n: Math.floor(diff / 3600) });
    return t('account.sessions_days_ago', { n: Math.floor(diff / 86400) });
}

/**
 * Both endpoints used to be fired and then followed by an unconditional
 * reload — revoke() never looked at the response at all, so a failure just
 * reloaded a page with the session still on it.
 */
async function send(url: string, body?: Record<string, unknown>): Promise<boolean> {
    error.value = '';

    try {
        const res = await fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            ...(body ? { body: JSON.stringify(body) } : {}),
        });

        const data = await res.json().catch(() => null);

        if (!res.ok) {
            error.value = data?.message ?? t('account.sessions_network_error');
            return false;
        }

        return true;
    } catch {
        error.value = t('account.sessions_network_error');
        return false;
    }
}

async function revoke(id: string) {
    revoking.value = id;
    const done = await send(route('account.sessions.destroy', id));
    revoking.value = null;

    if (done) window.location.reload();
}

async function revokeAll() {
    loading.value = true;
    const done = await send(route('account.sessions.destroy-others'), { password: password.value });
    loading.value = false;

    if (done) window.location.reload();
}

const input = computed(() =>
    dark.value
        ? 'w-full rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 px-4 py-2.5 text-[14px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition'
        : 'w-full rounded-xl border border-zinc-200 bg-white text-zinc-900 px-4 py-2.5 text-[14px] placeholder:text-zinc-400 focus:outline-none focus:border-blue-400/60 focus:ring-2 focus:ring-blue-500/10 transition',
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
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.sessions_title') }}
                </h2>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.sessions_hint') }}</p>
            </div>
            <span
                class="text-[11px] font-bold px-2.5 py-1 rounded-full border shrink-0"
                :class="dark ? 'border-zinc-800/70 bg-zinc-900/50 text-zinc-400' : 'border-zinc-200 bg-zinc-50 text-zinc-500'"
            >
                {{ sessions.length === 1
                    ? t('account.sessions_one', { count: 1 })
                    : t('account.sessions_many', { count: sessions.length }) }}
            </span>
        </div>

        <div class="p-6 flex flex-col gap-3">
            <p
                v-if="error"
                role="alert"
                class="px-4 py-3 rounded-xl border text-[13px] font-semibold"
                :class="dark ? 'border-red-500/20 bg-red-500/10 text-red-400' : 'border-red-200 bg-red-50 text-red-700'"
            >
                {{ error }}
            </p>
            <p v-if="!sessions.length" class="text-[13px]" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                {{ t('account.sessions_empty') }}
            </p>

            <div
                v-for="session in sessions"
                :key="session.id"
                class="flex items-center gap-3 rounded-xl border px-4 py-3"
                :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]/50' : 'border-zinc-100 bg-zinc-50'"
            >
                <div
                    class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center"
                    :class="dark ? 'bg-blue-500/10 border border-blue-500/20' : 'bg-blue-50 border border-blue-100'"
                >
                    <component
                        :is="session.device.mobile ? Smartphone : Monitor"
                        :size="15"
                        :stroke-width="1.8"
                        aria-hidden="true"
                        :class="dark ? 'text-blue-400' : 'text-blue-600'"
                    />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                            {{ session.device.browser }} {{ t('account.sessions_on') }} {{ session.device.os }}
                        </p>
                        <span
                            v-if="session.is_current"
                            class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                            :class="dark
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400'
                                : 'border-emerald-600/30 bg-emerald-50 text-emerald-700'"
                        >
                            {{ t('account.sessions_this_device') }}
                        </span>
                    </div>
                    <p class="text-[11px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                        {{ session.ip_address ?? t('account.sessions_unknown_ip') }} · {{ formatTime(session.last_activity) }}
                    </p>
                </div>
                <button
                    v-if="!session.is_current"
                    type="button"
                    :disabled="revoking !== null"
                    :aria-label="`${t('account.sessions_revoke')} — ${session.device.browser} ${t('account.sessions_on')} ${session.device.os}`"
                    class="text-[12px] font-semibold shrink-0 transition disabled:opacity-50 disabled:cursor-not-allowed
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 rounded"
                    :class="dark ? 'text-zinc-400 hover:text-red-400' : 'text-zinc-500 hover:text-red-600'"
                    @click="revoke(session.id)"
                >
                    {{ revoking === session.id ? '…' : t('account.sessions_revoke') }}
                </button>
            </div>

            <div v-if="sessions.length > 1" class="flex flex-col gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                <div>
                    <h3 class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                        {{ t('account.sessions_sign_out_others') }}
                    </h3>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">{{ t('account.sessions_confirm_hint') }}</p>
                </div>

                <div
                    class="flex items-start gap-2.5 rounded-xl border px-4 py-3"
                    :class="dark ? 'border-zinc-800/60 bg-zinc-900/40' : 'border-zinc-200 bg-zinc-50'"
                >
                    <Info :size="14" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-zinc-400' : 'text-zinc-500'" />
                    <p class="text-[12px] leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-600'">
                        {{ t('account.sessions_remember_note') }}
                    </p>
                </div>

                <button
                    type="button"
                    :aria-expanded="showConfirm"
                    class="self-start text-[13px] font-semibold transition rounded
                           focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                    :class="dark ? 'text-blue-400 hover:text-blue-300' : 'text-blue-600 hover:text-blue-700'"
                    @click="showConfirm = !showConfirm"
                >
                    {{ t('account.sessions_sign_out_others') }}
                </button>

                <div v-if="showConfirm" class="flex flex-wrap gap-2">
                    <label for="sessions_password" class="sr-only">{{ t('account.sessions_your_pw') }}</label>
                    <input
                        id="sessions_password"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        :placeholder="t('account.sessions_your_pw')"
                        :class="[input, 'flex-1 min-w-[12rem]']"
                        @keyup.enter="revokeAll"
                    />
                    <button
                        type="button"
                        :disabled="loading || !password"
                        class="border font-bold text-[12px] px-4 py-2.5 rounded-xl transition disabled:opacity-60
                               disabled:cursor-not-allowed shrink-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                        :class="dark
                            ? 'bg-red-500/10 border-red-500/30 text-red-400 hover:bg-red-500/20'
                            : 'bg-red-50 border-red-300 text-red-700 hover:bg-red-100'"
                        @click="revokeAll"
                    >
                        {{ t('account.sessions_revoke_all') }}
                    </button>
                    <button
                        type="button"
                        class="text-[12px] font-semibold transition shrink-0"
                        :class="dark ? 'text-zinc-400 hover:text-zinc-100' : 'text-zinc-500 hover:text-zinc-900'"
                        @click="showConfirm = false; password = ''; error = ''"
                    >
                        {{ t('account.sessions_cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
