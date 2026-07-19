<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    ExternalLink,
    Gamepad2,
    Info,
    Link2,
    Mail,
    MessageSquare,
    ShieldAlert,
    Unlink,
    type FunctionalComponent,
} from '@lucide/vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed } from 'vue';

interface Connected {
    provider: string;
    username: string | null;
    avatar_url: string | null;
    connected_at: string;
}
interface Provider {
    id: string;
    name: string;
    icon: string;
    enabled: boolean;
}

const props = defineProps<{
    connectedAccounts: Connected[];
    oauthProviders: Provider[];
    hasPassword: boolean;
}>();

const emit = defineEmits<{ 'update:activeTab': [value: string] }>();

const { t } = useLocale();
const { theme } = useTheme();
const dark = computed(() => theme.value === 'dark');

/**
 * Providers register a Lucide icon name. Every provider used to render the
 * same generic link glyph, so Steam, Discord and Google were indistinguishable
 * at a glance. Extensions can register their own, hence the fallback.
 */
const ICONS: Record<string, FunctionalComponent> = {
    Gamepad2,
    MessageSquare,
    Mail,
};

function iconFor(provider: Provider): FunctionalComponent {
    return ICONS[provider.icon] ?? Link2;
}

function connectionFor(providerId: string): Connected | undefined {
    return props.connectedAccounts.find((c) => c.provider === providerId);
}

/**
 * True when unlinking this provider would leave the account with no way in:
 * OAuth signups get a random password nobody knows, so the provider is the
 * only credential. The server enforces this too — this is just so the page can
 * explain it rather than fail on click.
 */
function isOnlyLogin(providerId: string): boolean {
    if (props.hasPassword) return false;
    if (!connectionFor(providerId)) return false;

    return props.connectedAccounts.length === 1;
}

function disconnect(providerId: string) {
    if (window.confirm(t('account.disconnect_confirm'))) {
        router.delete(route('oauth.disconnect', providerId), { preserveScroll: true });
    }
}

function connect(providerId: string) {
    window.location.href = route('oauth.redirect', providerId);
}
</script>

<template>
    <div
        class="rounded-2xl border overflow-hidden"
        :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'"
    >
        <div
            class="px-6 py-4 border-b flex items-start gap-2.5"
            :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]' : 'border-zinc-100 bg-zinc-50'"
        >
            <Link2 :size="15" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-blue-400' : 'text-blue-600'" />
            <div>
                <h2 class="text-[15px] font-black tracking-tight" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ t('account.oauth_title') }}
                </h2>
                <p class="text-[12px] mt-0.5 leading-relaxed" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                    {{ t('account.oauth_subtitle') }}
                </p>
            </div>
        </div>

        <div class="p-6">
            <p
                v-if="oauthProviders.length === 0"
                class="text-center py-8 text-[13px]"
                :class="dark ? 'text-zinc-500' : 'text-zinc-500'"
            >
                {{ t('account.no_providers') }}
            </p>

            <div v-else class="flex flex-col gap-2">
                <div
                    v-for="provider in oauthProviders"
                    :key="provider.id"
                    class="flex items-center justify-between gap-3 px-4 py-3.5 rounded-xl border transition"
                    :class="dark ? 'border-zinc-800/60 bg-[#1a1a1e]/40' : 'border-zinc-100 bg-zinc-50'"
                >
                    <div class="flex items-center gap-3 min-w-0">
                        <div
                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                            :class="connectionFor(provider.id)
                                ? dark ? 'bg-emerald-500/10 border border-emerald-500/20' : 'bg-emerald-50 border border-emerald-100'
                                : dark ? 'bg-zinc-800/60 border border-zinc-800' : 'bg-white border border-zinc-200'"
                        >
                            <component
                                :is="iconFor(provider)"
                                :size="13"
                                :stroke-width="1.75"
                                aria-hidden="true"
                                :class="connectionFor(provider.id)
                                    ? dark ? 'text-emerald-400' : 'text-emerald-800'
                                    : dark ? 'text-zinc-400' : 'text-zinc-500'"
                            />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-200' : 'text-zinc-800'">
                                    {{ provider.name }}
                                </p>
                                <span
                                    v-if="isOnlyLogin(provider.id)"
                                    class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border"
                                    :class="dark
                                        ? 'border-amber-500/30 bg-amber-500/10 text-amber-300'
                                        : 'border-amber-300 bg-amber-50 text-amber-800'"
                                >
                                    <ShieldAlert :size="10" :stroke-width="2.4" />
                                    {{ t('account.oauth_last_login_short') }}
                                </span>
                            </div>

                            <p
                                v-if="connectionFor(provider.id)"
                                class="text-[11px] mt-0.5"
                                :class="dark ? 'text-zinc-500' : 'text-zinc-500'"
                            >
                                <span class="font-semibold" :class="dark ? 'text-emerald-400' : 'text-emerald-800'">
                                    {{ t('account.connected') }}</span
                                ><template v-if="connectionFor(provider.id)?.username">
                                    — @{{ connectionFor(provider.id)?.username }}</template
                                >
                                · {{ t('account.oauth_connected_since', { date: connectionFor(provider.id)?.connected_at }) }}
                            </p>
                            <p v-else class="text-[11px] mt-0.5" :class="dark ? 'text-zinc-500' : 'text-zinc-500'">
                                {{ provider.enabled ? t('account.not_connected') : t('account.oauth_disabled_hint') }}
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="connectionFor(provider.id) && !isOnlyLogin(provider.id)"
                        type="button"
                        :aria-label="`${t('account.disconnect')} — ${provider.name}`"
                        class="flex items-center gap-1.5 text-[12px] font-semibold px-3 py-2 rounded-lg border transition shrink-0
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500"
                        :class="dark
                            ? 'border-zinc-800 text-zinc-400 hover:text-red-400 hover:border-red-500/30 hover:bg-red-500/10'
                            : 'border-zinc-200 text-zinc-500 hover:text-red-600 hover:border-red-300 hover:bg-red-50'"
                        @click="disconnect(provider.id)"
                    >
                        <Unlink :size="12" :stroke-width="1.75" />
                        {{ t('account.disconnect') }}
                    </button>

                    <button
                        v-else-if="isOnlyLogin(provider.id)"
                        type="button"
                        class="flex items-center gap-1.5 text-[12px] font-bold px-3 py-2 rounded-lg border transition shrink-0
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        :class="dark
                            ? 'border-blue-500/30 bg-blue-500/10 text-blue-400 hover:bg-blue-500/20'
                            : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100'"
                        @click="emit('update:activeTab', 'security')"
                    >
                        {{ t('account.oauth_set_password') }}
                    </button>

                    <button
                        v-else-if="provider.enabled"
                        type="button"
                        :aria-label="`${t('account.connect')} — ${provider.name}`"
                        class="flex items-center gap-1.5 text-[12px] font-bold px-3 py-2 rounded-lg border transition shrink-0
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                        :class="dark
                            ? 'border-blue-500/30 bg-blue-500/10 text-blue-400 hover:bg-blue-500/20'
                            : 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100'"
                        @click="connect(provider.id)"
                    >
                        <ExternalLink :size="12" :stroke-width="1.75" />
                        {{ t('account.connect') }}
                    </button>

                    <span v-else class="text-[12px] font-semibold shrink-0" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">
                        {{ t('account.unavailable') }}
                    </span>
                </div>
            </div>

            <div
                v-if="!hasPassword && connectedAccounts.length > 0"
                class="mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3"
                :class="dark ? 'border-amber-500/20 bg-amber-500/10' : 'border-amber-300 bg-amber-50'"
            >
                <Info :size="14" :stroke-width="1.8" class="mt-0.5 shrink-0" :class="dark ? 'text-amber-300' : 'text-amber-700'" />
                <p class="text-[12px] leading-relaxed" :class="dark ? 'text-amber-200' : 'text-amber-900'">
                    {{ t('account.oauth_last_login') }}
                </p>
            </div>
        </div>
    </div>
</template>
