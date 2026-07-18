<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import Profile from '@/pages/Account/Profile.vue';
import Security from '@/pages/Account/Security.vue';
import Preferences from '@/pages/Account/Preferences.vue';
import ConnectedAccounts from '@/pages/Account/ConnectedAccounts.vue';
import TwoFactor from '@/pages/Account/TwoFactor.vue';
import Sessions from '@/pages/Account/Sessions.vue';
import Notifications from '@/pages/Account/Notifications.vue';
import BlockedUsers from '@/pages/Account/BlockedUsers.vue';
import DangerZone from '@/pages/Account/DangerZone.vue';
import ActivityLog from '@/pages/Account/ActivityLog.vue';
import EmailPreferences from '@/pages/Account/EmailPreferences.vue';
import AccountPage from '@/components/Account/AccountPage.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';
import { computed, ref, watch } from 'vue';

interface Account {
    name: string; username: string; display_name: string | null;
    email: string; avatar: string | null; banner: string | null;
    bio: string | null; location: string | null; website: string | null;
    profile_privacy: 'public' | 'members' | 'private';
    timezone: string | null; locale: string | null; verified: boolean;
    created_at: string; last_login_at: string | null;
    two_factor_enabled: boolean; two_factor_recovery_codes: string[] | null;
    can_change_username: boolean; username_change_available_at: string | null;
    notification_preferences: Record<string, boolean> | string[];
}
interface SessionItem {
    id: string; ip_address: string | null; last_activity: number; is_current: boolean;
    device: { browser: string; os: string; mobile: boolean };
}
interface Connected { provider: string; username: string | null; avatar_url: string | null; connected_at: string }
interface Provider { id: string; name: string; icon: string; enabled: boolean }
interface NotifData { type: string; level?: string; message?: string; sender_username?: string; preview?: string; conversation_id?: number; action_url?: string; action_label?: string }
interface Notif { id: string; type: string; data: NotifData; read: boolean; created_at: string }
interface BlockEntry { id: number; user: { id: number; username: string; display_name: string; avatar: string | null }; blocked_at: string }

const props = defineProps<{
    account: Account;
    sessions: SessionItem[];
    connectedAccounts: Connected[];
    oauthProviders: Provider[];
    hasPassword: boolean;
    notifications: { data: Notif[]; links: any; meta: any };
    blocks: BlockEntry[];
    unreadNotifications: number;
    unreadMessages: number;
    loginHistory: { data: any[]; links: any; meta: any };
}>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ flash: { success: string | null; error: string | null } }>();
const flash = computed(() => page.props.flash);
const TAB_IDS = [
    'profile', 'security', 'sessions', 'prefs', 'connected', 'notifications',
    'activity', 'blocked', 'email-prefs', 'danger',
];
const LAST_TAB_KEY = 'hc-account-last-tab';

function initialTab(): string {
    const stored = localStorage.getItem(LAST_TAB_KEY);
    return stored && TAB_IDS.includes(stored) ? stored : 'profile';
}

const activeTab = ref(initialTab());

watch(activeTab, (tab) => {
    localStorage.setItem(LAST_TAB_KEY, tab);
});
</script>

<template>
    <Head :title="t('account.my_account')" />

    <AccountPage
        :active-tab="activeTab"
        :unread-notifications="unreadNotifications"
        :unread-messages="unreadMessages"
        :standalone="false"
        @update:active-tab="activeTab = $event"
    >
        <!-- Email unverified notice -->
        <div
            v-if="!account.verified"
            class="flex items-start gap-3 rounded-xl border px-4 py-3"
            :class="dark ? 'border-amber-500/20 bg-amber-500/8' : 'border-amber-200 bg-amber-50'"
        >
            <AlertTriangle :size="15" :stroke-width="1.75" class="text-amber-500 mt-0.5 shrink-0" />
            <div>
                <p class="text-[13px] font-bold" :class="dark ? 'text-zinc-100' : 'text-zinc-900'">{{ t('account.email_not_verified') }}</p>
                <Link :href="route('verification.notice')" class="text-amber-500 text-[12px] font-semibold hover:underline">
                    {{ t('account.verify_now') }} →
                </Link>
            </div>
        </div>

        <!-- Flash messages -->
        <div v-if="flash?.success" class="px-4 py-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500 text-[13px] font-semibold">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="px-4 py-3 rounded-xl border border-red-500/20 bg-red-500/10 text-red-400 text-[13px] font-semibold">
            {{ flash.error }}
        </div>

        <template v-if="activeTab === 'profile'">
                            <Profile :account="account" />
                        </template>
                        <template v-else-if="activeTab === 'security'">
                            <Security />
                            <TwoFactor :enabled="account.two_factor_enabled" :recovery-codes="account.two_factor_recovery_codes" />
                        </template>
                        <template v-else-if="activeTab === 'sessions'">
                            <Sessions :sessions="sessions" />
                        </template>
                        <template v-else-if="activeTab === 'prefs'">
                            <Preferences :account="account" />
                        </template>
                        <template v-else-if="activeTab === 'connected'">
                            <ConnectedAccounts
                                :connected-accounts="connectedAccounts"
                                :oauth-providers="oauthProviders"
                                :has-password="hasPassword"
                                @update:active-tab="activeTab = $event"
                            />
                        </template>
                        <template v-else-if="activeTab === 'notifications'">
                            <Notifications :notifications="notifications" />
                        </template>
                        <template v-else-if="activeTab === 'activity'">
                            <ActivityLog :history="loginHistory" />
                        </template>
                        <template v-else-if="activeTab === 'blocked'">
                            <BlockedUsers :blocks="blocks" />
                        </template>
                        <template v-else-if="activeTab === 'email-prefs'">
                            <EmailPreferences :preferences="account.notification_preferences" />
                        </template>
        <template v-else-if="activeTab === 'danger'">
            <DangerZone :username="account.username" />
        </template>
    </AccountPage>
</template>
