<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    User, ShieldCheck, Monitor, Settings, Link2, LogOut,
    BadgeCheck, ExternalLink, Bell, MessageSquare, Star,
    History, Ban, Trash, MailCheck, ThumbsUp, Gift, Trophy, Puzzle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = defineProps<{
    activeTab: string;
    unreadNotifications?: number;
    unreadMessages?: number;
    /** True when this sidebar is rendered on a standalone page (not the tabbed Account/Index.vue). */
    standalone?: boolean;
}>();
const emit = defineEmits<{ 'update:activeTab': [value: string] }>();

const LAST_TAB_KEY = 'hc-account-last-tab';

/** Tabs that live on their own dedicated route rather than as an Index.vue tab. */
const routableTabs: Record<string, string> = {
    favorites: 'account.favorites',
    messages: 'account.messages.index',
};

function selectTab(tabId: string) {
    if (routableTabs[tabId]) {
        router.visit(route(routableTabs[tabId]));
        return;
    }
    if (props.standalone) {
        localStorage.setItem(LAST_TAB_KEY, tabId);
        router.visit(route('account.index'));
        return;
    }
    emit('update:activeTab', tabId);
}

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');
const page = usePage<{ auth: { user: any } | null; accountTabs?: ExtTab[] }>();
const user = computed(() => page.props.auth?.user);

// Extension-registered account tabs (each navigates to its own route).
interface ExtTab { key: string; label: string; url: string; icon: string }
const extTabIcons: Record<string, Component> = { ThumbsUp, Gift, Trophy, Star, Puzzle };
const extensionTabs = computed(() => page.props.accountTabs ?? []);
function extTabIcon(name: string): Component { return extTabIcons[name] ?? Puzzle; }
function isExtTabActive(url: string): boolean {
    return page.url === url || page.url.startsWith(url + '?');
}
function visitExtTab(url: string) { router.visit(url); }

const tabs = computed(() => [
    { id: 'profile',      label: t('account.tab_profile'),   icon: User,          badge: 0 },
    { id: 'security',     label: t('account.tab_security'),  icon: ShieldCheck,   badge: 0 },
    { id: 'sessions',     label: t('account.tab_sessions'),  icon: Monitor,       badge: 0 },
    { id: 'prefs',        label: t('account.tab_prefs'),     icon: Settings,      badge: 0 },
    { id: 'connected',    label: t('account.tab_connected'), icon: Link2,         badge: 0 },
    { id: 'notifications', label: t('account.tab_notifications'), icon: Bell,          badge: props.unreadNotifications ?? 0 },
    { id: 'messages',      label: t('account.tab_messages'),      icon: MessageSquare, badge: props.unreadMessages ?? 0 },
    { id: 'favorites',     label: t('account.tab_favorites'),     icon: Star,          badge: 0 },
    { id: 'activity',      label: t('account.tab_activity'),      icon: History,       badge: 0 },
    { id: 'blocked',       label: t('account.tab_blocked'),       icon: Ban,           badge: 0 },
    { id: 'email-prefs',   label: t('account.tab_email_prefs'),   icon: MailCheck,     badge: 0 },
]);

const dangerTab = computed(() => ({ id: 'danger', label: t('account.tab_danger'), icon: Trash }));

function logout() { router.post(route('logout')); }

function avatarBg(name: string) {
    const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#10b981', '#f59e0b', '#ef4444'];
    return colors[(name.charCodeAt(0) ?? 0) % colors.length];
}
</script>

<template>
    <aside class="flex flex-col gap-2 w-56 shrink-0 sticky top-24">
        <!-- User card -->
        <div class="rounded-xl border p-3.5 mb-1" :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden shrink-0">
                    <img v-if="user?.avatar" :src="user.avatar" :alt="user.username" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full flex items-center justify-center text-[15px] font-black text-white uppercase" :style="{ backgroundColor: avatarBg(user?.username ?? '?') }">{{ (user?.username ?? '?')[0] }}</div>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <p class="text-[13px] font-bold truncate" :class="dark ? 'text-zinc-100' : 'text-zinc-800'">{{ user?.display_name || user?.username }}</p>
                        <BadgeCheck v-if="user?.verified" :size="12" :stroke-width="2.2" class="text-blue-400 shrink-0" />
                    </div>
                    <p class="text-[11px] font-mono truncate" :class="dark ? 'text-zinc-500' : 'text-zinc-400'">@{{ user?.username }}</p>
                </div>
            </div>
            <a v-if="user?.username" :href="route('profile.show', user.username)"
                class="flex items-center justify-center gap-1.5 w-full py-1.5 rounded-lg border text-[11px] font-semibold transition"
                :class="dark ? 'border-zinc-800/70 text-zinc-500 hover:text-zinc-200 hover:border-zinc-700' : 'border-zinc-200 text-zinc-400 hover:text-zinc-500 hover:border-zinc-300'">
                <ExternalLink :size="11" :stroke-width="1.8" />
                {{ t('account.view_public_profile') }}
            </a>
        </div>

        <!-- Nav -->
        <nav class="flex flex-col gap-0.5">
            <button v-for="tab in tabs" :key="tab.id" type="button"
                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13px] font-semibold text-left transition w-full"
                :class="activeTab === tab.id
                    ? dark ? 'bg-blue-500/12 text-blue-400 border border-blue-500/25' : 'bg-blue-50 text-blue-600 border border-blue-200'
                    : dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.04] border border-transparent' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 border border-transparent'"
                @click="selectTab(tab.id)">
                <component :is="tab.icon" :size="14" :stroke-width="1.9" class="shrink-0" />
                <span class="flex-1 truncate">{{ tab.label }}</span>
                <span v-if="tab.badge > 0" class="ml-auto text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center bg-blue-500 text-white">
                    {{ tab.badge > 99 ? '99+' : tab.badge }}
                </span>
            </button>

            <!-- Extension-registered tabs -->
            <button v-for="tab in extensionTabs" :key="tab.key" type="button"
                class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13px] font-semibold text-left transition w-full"
                :class="isExtTabActive(tab.url)
                    ? dark ? 'bg-blue-500/12 text-blue-400 border border-blue-500/25' : 'bg-blue-50 text-blue-600 border border-blue-200'
                    : dark ? 'text-zinc-500 hover:text-zinc-200 hover:bg-white/[0.04] border border-transparent' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-100 border border-transparent'"
                @click="visitExtTab(tab.url)">
                <component :is="extTabIcon(tab.icon)" :size="14" :stroke-width="1.9" class="shrink-0" />
                <span class="flex-1 truncate">{{ tab.label }}</span>
            </button>
        </nav>

        <!-- Divider -->
        <div class="my-1 border-t" :class="dark ? 'border-zinc-800/60' : 'border-zinc-100'" />

        <!-- Danger -->
        <button type="button"
            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13px] font-semibold w-full transition border"
            :class="activeTab === 'danger'
                ? 'bg-red-500/10 text-red-400 border-red-500/25'
                : dark ? 'text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20 border-transparent' : 'text-zinc-400 hover:text-red-500 hover:bg-red-50 hover:border-red-100 border-transparent'"
            @click="selectTab('danger')">
            <component :is="dangerTab.icon" :size="14" :stroke-width="1.9" class="shrink-0" />
            {{ dangerTab.label }}
        </button>

        <!-- Logout -->
        <button type="button"
            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13px] font-semibold w-full transition border border-transparent"
            :class="dark ? 'text-zinc-500 hover:text-red-400 hover:bg-red-500/8 hover:border-red-500/20' : 'text-zinc-400 hover:text-red-500 hover:bg-red-50 hover:border-red-100'"
            @click="logout">
            <LogOut :size="14" :stroke-width="1.9" class="shrink-0" />
            {{ t('account.sign_out') }}
        </button>
    </aside>
</template>
