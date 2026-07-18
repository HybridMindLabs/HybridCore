<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Lightbulb } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import ExtensionSlot from '@/components/Core/ExtensionSlot.vue';
import Breadcrumb from '@/components/UI/Breadcrumb.vue';
import AccountSidebar from '@/components/Account/AccountSidebar.vue';
import { useTheme } from '@/composables/useTheme';
import { useLocale } from '@/composables/useLocale';

const props = withDefaults(defineProps<{
    activeTab: string;
    unreadNotifications?: number;
    unreadMessages?: number;
    /** False when used from the tabbed Account/Index.vue — tab clicks switch in place. */
    standalone?: boolean;
    /** Current section name shown in the breadcrumb (e.g. "Messages", "Favorites"). */
    section?: string;
}>(), { standalone: true });

const emit = defineEmits<{ 'update:activeTab': [value: string] }>();

const { theme } = useTheme();
const { t } = useLocale();
const dark = computed(() => theme.value === 'dark');

const heading = computed(() => props.section ?? t('account.my_account'));

/**
 * Every section explains itself. `t()` returns the key when a translation is
 * missing, so fall back to the generic subtitle rather than printing
 * "account.desc_whatever" at the reader.
 */
const sectionDescription = computed(() => {
    const key = `account.desc_${props.activeTab.replace('-', '_')}`;
    const text = t(key);
    return text === key ? t('account.account_subtitle') : text;
});

/**
 * Up to three tips for the current section. Keys are optional — the loop stops
 * at the first missing one, so a section with two tips needs no extra config.
 */
const helpTips = computed(() => {
    const base = `account.help_${props.activeTab.replace('-', '_')}`;
    const tips: string[] = [];

    for (let i = 1; i <= 3; i++) {
        const key = `${base}_${i}`;
        const text = t(key);
        if (text === key) break;
        tips.push(text);
    }

    return tips;
});

const mobileTabs = computed(() => [
    { id: 'profile', label: t('account.tab_profile'), badge: 0 },
    { id: 'security', label: t('account.tab_security'), badge: 0 },
    { id: 'sessions', label: t('account.tab_sessions'), badge: 0 },
    { id: 'prefs', label: t('account.tab_prefs'), badge: 0 },
    { id: 'connected', label: t('account.tab_connected'), badge: 0 },
    { id: 'notifications', label: t('account.tab_notifications'), badge: props.unreadNotifications ?? 0 },
    { id: 'messages', label: t('account.tab_messages'), badge: props.unreadMessages ?? 0 },
    { id: 'favorites', label: t('account.tab_favorites'), badge: 0 },
    { id: 'activity', label: t('account.tab_activity'), badge: 0 },
    { id: 'blocked', label: t('account.tab_blocked'), badge: 0 },
    { id: 'email-prefs', label: t('account.tab_email_prefs'), badge: 0 },
    { id: 'danger', label: t('account.tab_danger'), badge: 0 },
]);

/** Mirrors the sidebar: some sections are their own route, the rest are tabs. */
const routableTabs: Record<string, string> = {
    favorites: 'account.favorites',
    messages: 'account.messages.index',
};

function selectMobileTab(tabId: string) {
    if (routableTabs[tabId]) {
        router.visit(route(routableTabs[tabId]));
        return;
    }
    if (props.standalone) {
        localStorage.setItem('hc-account-last-tab', tabId);
        router.visit(route('account.index'));
        return;
    }
    emit('update:activeTab', tabId);
}
</script>

<template>
    <!-- These pages sit behind auth and hold personal data. The correct search
         behaviour is to stay out of the index entirely, not to be optimised. -->
    <Head>
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <PublicLayout>

        <section
            class="relative overflow-hidden border-b"
            :class="dark ? 'border-zinc-800/60 bg-[#09090b]' : 'border-zinc-200 bg-zinc-50'"
            :aria-label="t('account.my_account')"
        >
            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <div class="hc-hero-glow absolute -top-32 left-1/4 w-[520px] h-[360px] rounded-full blur-[120px]"
                    :class="dark ? 'bg-blue-500/8' : 'bg-blue-400/10'" />
                <div class="hc-hero-glow hc-hero-glow--slow absolute -top-16 right-1/4 w-[300px] h-[280px] rounded-full blur-[100px]"
                    :class="dark ? 'bg-violet-500/6' : 'bg-violet-400/8'" />
                <div class="absolute inset-0" :class="dark ? 'opacity-50' : 'opacity-[0.35]'"
                    :style="`background-image:radial-gradient(circle,${dark ? 'rgba(255,255,255,0.035)' : 'rgba(24,24,27,0.05)'} 1px,transparent 1px);background-size:28px 28px`" />
            </div>

            <div class="relative z-10 max-w-[1400px] mx-auto px-3 sm:px-6 py-8">
                <Breadcrumb :items="[
                    { label: t('navigation.nav_home'), href: route('home') },
                    section
                        ? { label: t('account.my_account'), href: route('account.index') }
                        : { label: t('account.my_account') },
                    ...(section ? [{ label: section }] : []),
                ]" />

                <h1 class="hc-hero-in mt-3 text-[26px] sm:text-[32px] font-black tracking-tight leading-tight"
                    :class="dark ? 'text-zinc-100' : 'text-zinc-900'">
                    {{ heading }}
                </h1>
                <p class="hc-hero-in hc-hero-in--1 text-[13.5px] leading-relaxed mt-1.5 max-w-xl"
                   :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                    <slot name="subtitle">{{ sectionDescription }}</slot>
                </p>
            </div>
        </section>

        <!-- Section nav for phones. The sidebar is `hidden sm:block`, so below
             640px there was previously no way to move between sections at all. -->
        <nav class="sm:hidden border-b overflow-x-auto"
            :class="dark ? 'border-zinc-800/60 bg-[#0b0b0e]' : 'border-zinc-200 bg-white'"
            :aria-label="t('account.mobile_nav_label')">
            <div class="flex items-center gap-1 px-3 py-2 w-max">
                <button v-for="tab in mobileTabs" :key="tab.id" type="button"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-[12.5px] font-semibold whitespace-nowrap transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50"
                    :class="activeTab === tab.id
                        ? 'bg-blue-600 text-white'
                        : dark ? 'text-zinc-400 hover:text-zinc-100 hover:bg-white/[0.05]' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100'"
                    :aria-current="activeTab === tab.id ? 'page' : undefined"
                    @click="selectMobileTab(tab.id)">
                    {{ tab.label }}
                    <span v-if="tab.badge" class="px-1.5 py-0.5 rounded-md text-[10px] font-bold tabular-nums"
                        :class="activeTab === tab.id ? 'bg-black/25 text-white' : 'bg-red-500 text-white'">{{ tab.badge }}</span>
                </button>
            </div>
        </nav>

        <!-- Three columns on wide screens. Stretching the forms to fill a 1900px
             window would only make every input harder to scan, so the extra room
             goes to a help column instead of to longer fields. -->
        <div class="px-3 sm:px-6 py-8">
            <div class="max-w-[1400px] mx-auto">
                <div class="grid gap-6 items-start lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[15rem_minmax(0,1fr)_18rem]">
                    <div class="hidden sm:block">
                        <AccountSidebar
                            :active-tab="activeTab"
                            :unread-notifications="unreadNotifications"
                            :unread-messages="unreadMessages"
                            :standalone="standalone"
                            @update:active-tab="emit('update:activeTab', $event)"
                        />
                    </div>

                    <main class="min-w-0 flex flex-col gap-4">
                        <slot />
                        <ExtensionSlot name="account.panel.bottom" :context="{ activeTab }" />
                    </main>

                    <!-- Help column: hidden below xl, and skipped entirely for
                         sections that have nothing useful to add. -->
                    <aside v-if="helpTips.length" class="hidden xl:block sticky top-24"
                        :aria-label="t('account.help_title')">
                        <div class="hc-reveal rounded-2xl border overflow-hidden"
                            :class="dark ? 'border-zinc-800/70 bg-[#111113]' : 'border-zinc-200 bg-white shadow-[0_1px_4px_rgba(0,0,0,0.06)]'">
                            <div class="flex items-center gap-2 px-4 py-3 border-b"
                                :class="dark ? 'border-zinc-800/60 bg-[#17171a]' : 'border-zinc-200 bg-zinc-50'">
                                <Lightbulb :size="13" :stroke-width="2" class="text-amber-500" aria-hidden="true" />
                                <h2 class="text-[11px] font-black uppercase tracking-widest"
                                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">{{ t('account.help_title') }}</h2>
                            </div>
                            <ul class="p-4 flex flex-col gap-3">
                                <li v-for="(tip, i) in helpTips" :key="i"
                                    class="hc-reveal flex items-start gap-2.5 text-[12.5px] leading-relaxed"
                                    :style="{ animationDelay: 0.05 + i * 0.05 + 's' }"
                                    :class="dark ? 'text-zinc-400' : 'text-zinc-600'">
                                    <span class="mt-1.5 w-1 h-1 rounded-full shrink-0"
                                        :class="dark ? 'bg-zinc-600' : 'bg-zinc-400'" aria-hidden="true" />
                                    {{ tip }}
                                </li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
