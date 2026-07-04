<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link2, Unlink, ExternalLink } from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

interface Connected { provider: string; username: string | null; avatar_url: string | null; connected_at: string }
interface Provider { id: string; name: string; icon: string; enabled: boolean }

const props = defineProps<{ connectedAccounts: Connected[]; oauthProviders: Provider[] }>();

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

function isConnected(providerId: string): Connected | undefined {
    return props.connectedAccounts.find((c) => c.provider === providerId);
}

function disconnect(providerId: string) {
    if (confirm(t('account.disconnect_confirm'))) {
        router.delete(route('oauth.disconnect', providerId));
    }
}

function connect(providerId: string) {
    window.location.href = route('oauth.redirect', providerId);
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
        <div class="px-6 py-4 border-b" :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'">
            <p class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('account.connected_accounts') }}</p>
            <p class="text-[12px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">{{ t('account.connected_accounts_hint') }}</p>
        </div>

        <div class="p-6">
            <div v-if="oauthProviders.length === 0" class="text-center py-8 text-[13px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">
                {{ t('account.no_providers') }}
            </div>

            <div v-else class="flex flex-col gap-2">
                <div
                    v-for="provider in oauthProviders"
                    :key="provider.id"
                    class="flex items-center justify-between px-4 py-3.5 rounded-xl border transition"
                    :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]/40' : 'border-zinc-100 bg-zinc-50'"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                            :class="dark ? 'bg-blue-500/10 border border-blue-500/20' : 'bg-blue-50'"
                        >
                            <Link2 :size="13" :stroke-width="1.75" class="text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">{{ provider.name }}</p>
                            <p v-if="isConnected(provider.id)" class="text-[11px] font-semibold text-emerald-400">
                                {{ t('account.connected') }}{{ isConnected(provider.id)?.username ? ` — @${isConnected(provider.id)?.username}` : '' }}
                            </p>
                            <p v-else class="text-[11px]" :class="dark ? 'text-zinc-600' : 'text-zinc-400'">{{ t('account.not_connected') }}</p>
                        </div>
                    </div>

                    <button
                        v-if="isConnected(provider.id)"
                        type="button"
                        class="flex items-center gap-1.5 text-[12px] font-semibold px-3 py-2 rounded-lg border transition"
                        :class="dark
                            ? 'border-zinc-800 text-zinc-500 hover:text-red-400 hover:border-red-500/30 hover:bg-red-500/8'
                            : 'border-zinc-200 text-zinc-400 hover:text-red-500 hover:bg-red-50'"
                        @click="disconnect(provider.id)"
                    >
                        <Unlink :size="12" :stroke-width="1.75" />
                        {{ t('account.disconnect') }}
                    </button>
                    <button
                        v-else-if="provider.enabled"
                        type="button"
                        class="flex items-center gap-1.5 text-[12px] font-bold px-3 py-2 rounded-lg border transition"
                        :class="dark
                            ? 'border-blue-500/30 bg-blue-500/10 text-blue-400 hover:bg-blue-500/20'
                            : 'border-blue-200 bg-blue-50 text-blue-600 hover:bg-blue-100'"
                        @click="connect(provider.id)"
                    >
                        <ExternalLink :size="12" :stroke-width="1.75" />
                        {{ t('account.connect') }}
                    </button>
                    <span v-else class="text-[12px] font-semibold" :class="dark ? 'text-zinc-700' : 'text-zinc-300'">{{ t('account.unavailable') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>