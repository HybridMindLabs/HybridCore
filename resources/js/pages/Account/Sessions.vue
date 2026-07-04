<script setup lang="ts">
import { ref, computed } from 'vue';
import { Monitor, Smartphone } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

interface SessionItem {
    id: string; ip_address: string | null; last_activity: number; is_current: boolean;
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

function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function formatTime(ts: number): string {
    const diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60) return t('account.sessions_just_now');
    if (diff < 3600) return t('account.sessions_minutes_ago').replace(':n', String(Math.floor(diff / 60)));
    if (diff < 86400) return t('account.sessions_hours_ago').replace(':n', String(Math.floor(diff / 3600)));
    return t('account.sessions_days_ago').replace(':n', String(Math.floor(diff / 86400)));
}

async function revoke(id: string) {
    loading.value = true; error.value = '';
    try {
        await fetch(`/account/sessions/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        });
        window.location.reload();
    } catch { error.value = 'Network error'; }
    finally { loading.value = false; }
}

async function revokeAll() {
    loading.value = true; error.value = '';
    try {
        const res = await fetch('/account/sessions', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: password.value }),
        });
        const data = await res.json();
        if (!res.ok) { error.value = data.message ?? 'Error'; return; }
        window.location.reload();
    } catch { error.value = 'Network error'; }
    finally { loading.value = false; }
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
                <p class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('account.sessions_title') }}</p>
                <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.sessions_hint') }}</p>
            </div>
            <span class="text-[11px] font-bold px-2.5 py-1 rounded-full border" :class="dark ? 'border-zinc-800/70 bg-zinc-900/50 text-zinc-500' : 'border-zinc-200 bg-zinc-50 text-zinc-400'">
                {{ sessions.length === 1 ? t('account.sessions_one').replace(':count', '1') : t('account.sessions_many').replace(':count', String(sessions.length)) }}
            </span>
        </div>

        <div class="p-6 flex flex-col gap-3">
            <div v-if="error" class="px-4 py-3 rounded-xl border border-red-500/20 bg-red-500/10 text-red-400 text-[13px] font-semibold">{{ error }}</div>
            <p v-if="!sessions.length" class="text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('account.sessions_empty') }}</p>

            <div
                v-for="session in sessions"
                :key="session.id"
                class="flex items-center gap-3 rounded-xl border px-4 py-3"
                :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]/50' : 'border-zinc-100 bg-zinc-50'"
            >
                <div class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center" :class="dark ? 'bg-blue-500/10 border border-blue-500/20' : 'bg-blue-50'">
                    <component :is="session.device.mobile ? Smartphone : Monitor" :size="15" :stroke-width="1.8" class="text-blue-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ session.device.browser }} {{ t('account.sessions_on') }} {{ session.device.os }}</p>
                        <span v-if="session.is_current" class="text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-400">{{ t('account.sessions_this_device') }}</span>
                    </div>
                    <p class="text-[11px] mt-0.5" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ session.ip_address ?? t('account.sessions_unknown_ip') }} · {{ formatTime(session.last_activity) }}</p>
                </div>
                <button
                    v-if="!session.is_current"
                    type="button"
                    :disabled="loading"
                    class="text-[12px] font-semibold shrink-0 transition"
                    :class="dark ? 'text-zinc-600 hover:text-red-400' : 'text-zinc-400 hover:text-red-500'"
                    @click="revoke(session.id)"
                >{{ t('account.sessions_revoke') }}</button>
            </div>

            <div v-if="sessions.length > 1" class="flex flex-col gap-3 pt-4 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'">
                <div>
                    <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ t('account.sessions_sign_out_others') }}</p>
                    <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.sessions_confirm_hint') }}</p>
                </div>
                <button type="button" class="self-start text-[13px] font-semibold text-blue-400 hover:text-blue-300 transition" @click="showConfirm = !showConfirm">
                    {{ t('account.sessions_sign_out_others') }}
                </button>
                <div v-if="showConfirm" class="flex gap-2">
                    <input v-model="password" type="password" :placeholder="t('account.sessions_your_pw')" :class="input" @keyup.enter="revokeAll" />
                    <button type="button" :disabled="loading || !password" class="bg-red-500/10 border border-red-500/30 text-red-400 font-bold text-[12px] px-4 py-2.5 rounded-xl hover:bg-red-500/20 transition disabled:opacity-60 shrink-0" @click="revokeAll">
                        {{ t('account.sessions_revoke_all') }}
                    </button>
                    <button type="button" class="text-[12px] font-semibold transition" :class="dark ? 'text-zinc-500 hover:text-zinc-200' : 'text-zinc-400 hover:text-zinc-700'" @click="showConfirm = false">
                        {{ t('account.sessions_cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>