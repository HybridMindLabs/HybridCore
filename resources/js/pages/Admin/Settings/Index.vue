<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Settings, Globe, Search, Users, ShieldCheck, Puzzle, WrenchIcon, Scale, ArrowRight, Mail, Shield } from '@lucide/vue';
import { nextTick, onUnmounted, ref, watch } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import GeneralTab from '@/components/Admin/Settings/Tabs/GeneralTab.vue';
import LocalizationTab from '@/components/Admin/Settings/Tabs/LocalizationTab.vue';
import SeoTab from '@/components/Admin/Settings/Tabs/SeoTab.vue';
import UsersTab from '@/components/Admin/Settings/Tabs/UsersTab.vue';
import SecurityTab from '@/components/Admin/Settings/Tabs/SecurityTab.vue';
import OauthTab from '@/components/Admin/Settings/Tabs/OauthTab.vue';
import LegalTab from '@/components/Admin/Settings/Tabs/LegalTab.vue';
import ContactTab from '@/components/Admin/Settings/Tabs/ContactTab.vue';
import CaptchaTab from '@/components/Admin/Settings/Tabs/CaptchaTab.vue';
import ExtensionsTab from '@/components/Admin/Settings/Tabs/ExtensionsTab.vue';
import { settingsFormData, type PlatformSettings } from '@/components/Admin/Settings/settingsForm';

const props = defineProps<{
    settings: PlatformSettings;
    localeCatalog: Record<string, { name: string; native_name: string }>;
    timezones: string[];
    roles: { id: number; name: string; slug: string; color: string }[];
    extensionSettings: { slug: string; label: string; url: string; permission: string | null }[];
}>();

const tabs = [
    { id: 'general',      label: 'General',        icon: Settings },
    { id: 'localization', label: 'Localization',   icon: Globe },
    { id: 'seo',          label: 'SEO',            icon: Search },
    { id: 'users',        label: 'Users & Access', icon: Users },
    { id: 'security',     label: 'Security',       icon: ShieldCheck },
    { id: 'oauth',        label: 'OAuth',          icon: Puzzle },
    { id: 'legal',        label: 'Legal',          icon: Scale },
    { id: 'contact',      label: 'Contact',        icon: Mail },
    { id: 'captcha',      label: 'Captcha',        icon: Shield },
    { id: 'extensions',   label: 'Extensions',     icon: WrenchIcon },
];

/** Tabs that only link elsewhere and have nothing to save. */
const LINK_ONLY_TABS = ['legal', 'extensions'];

const activeTab = ref('general');
const form = useForm(settingsFormData(props.settings));

function submit() {
    // Default locale is edited only on the Localization tab — keep the legacy
    // top-level setting in sync so nothing reads a stale value.
    form.default_locale = form.loc_default_locale;
    form.put(route('admin.settings.update'), { preserveScroll: true });
}

// ── Settings search ─────────────────────────────────────────
// All tab panes stay in the DOM (v-show), so we can scan their labels.
const settingsSearch = ref('');
const searchResults = ref<{ tab: string; tabLabel: string; label: string }[]>([]);

watch(settingsSearch, (value) => {
    const query = value.trim().toLowerCase();
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }
    const results: { tab: string; tabLabel: string; label: string }[] = [];
    document.querySelectorAll<HTMLElement>('[data-tab]').forEach((pane) => {
        const tab = pane.dataset.tab!;
        const tabLabel = tabs.find((t) => t.id === tab)?.label ?? tab;
        pane.querySelectorAll('label, [role="switch"]').forEach((el) => {
            const text = (el.getAttribute('aria-label') ?? el.textContent ?? '').trim();
            if (text && text.toLowerCase().includes(query)) {
                results.push({ tab, tabLabel, label: text.split('—')[0].trim() });
            }
        });
    });
    searchResults.value = results.slice(0, 12);
});

let highlightTimer: ReturnType<typeof setTimeout> | null = null;
onUnmounted(() => { if (highlightTimer) clearTimeout(highlightTimer); });

function goToSetting(result: { tab: string; label: string }) {
    activeTab.value = result.tab;
    settingsSearch.value = '';
    searchResults.value = [];
    nextTick(() => {
        const pane = document.querySelector(`[data-tab="${result.tab}"]`);
        const target = Array.from(pane?.querySelectorAll<HTMLElement>('label, [role="switch"]') ?? [])
            .find((el) => (el.getAttribute('aria-label') ?? el.textContent ?? '').trim().startsWith(result.label));
        if (!target) return;
        // A switch is just a pill; highlight the row text next to it instead.
        const highlight = target.getAttribute('role') === 'switch'
            ? target.parentElement?.querySelector('p') ?? target
            : target;
        highlight.scrollIntoView({ behavior: 'smooth', block: 'center' });
        highlight.classList.add('!text-blue-400');
        if (highlightTimer) clearTimeout(highlightTimer);
        highlightTimer = setTimeout(() => highlight.classList.remove('!text-blue-400'), 2500);
    });
}
</script>

<template>
    <Head title="Settings" />
    <AdminLayout title="Settings">

        <PageHeader title="Platform Settings" description="Configure global options for your platform." :icon="Settings" />

        <!-- Settings search -->
        <div class="relative mb-5 max-w-md">
            <Search :size="13" :stroke-width="1.8" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-600" />
            <input
                v-model="settingsSearch"
                type="search"
                aria-label="Search settings"
                placeholder="Search settings… (e.g. avatar, locale, captcha)"
                class="w-full pl-8 pr-3 py-2 rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 text-[13px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 transition"
                @keydown.esc="settingsSearch = ''"
            />
            <div
                v-if="searchResults.length"
                class="absolute z-30 mt-1.5 w-full bg-admin-hover border border-zinc-800 rounded-xl shadow-xl overflow-hidden"
            >
                <button
                    v-for="(result, i) in searchResults"
                    :key="i"
                    type="button"
                    class="w-full flex items-center gap-2 px-3.5 py-2.5 text-left hover:bg-zinc-800/60 transition-colors border-b border-zinc-800/50 last:border-0"
                    @click="goToSetting(result)"
                >
                    <span class="text-[13px] text-zinc-200 truncate">{{ result.label }}</span>
                    <span class="ml-auto text-[11px] text-zinc-600 shrink-0">{{ result.tabLabel }}</span>
                    <ArrowRight :size="11" :stroke-width="2" class="text-zinc-700 shrink-0" />
                </button>
            </div>
            <div
                v-else-if="settingsSearch.trim().length >= 2"
                class="absolute z-30 mt-1.5 w-full bg-admin-hover border border-zinc-800 rounded-xl shadow-xl px-3.5 py-2.5"
            >
                <span class="text-[12px] text-zinc-600">No settings match "{{ settingsSearch }}".</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="flex gap-5 items-start">

                <!-- Vertical tab sidebar -->
                <div class="w-44 shrink-0 bg-admin-surface border border-zinc-800/70 rounded-xl p-1.5 sticky top-6" role="tablist" aria-orientation="vertical">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.id"
                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors text-left"
                        :class="activeTab === tab.id
                            ? 'bg-blue-500/10 text-blue-400'
                            : 'text-zinc-500 hover:text-zinc-100 hover:bg-zinc-800/50'"
                        @click="activeTab = tab.id"
                    >
                        <component :is="tab.icon" :size="13" :stroke-width="1.75" class="shrink-0" />
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Content area — takes full remaining width. Every pane stays
                     mounted (v-show) so the search above can scan all labels. -->
                <div class="flex-1 min-w-0">
                    <GeneralTab v-show="activeTab === 'general'" data-tab="general" :form="form" :settings="settings" :timezones="timezones" />
                    <LocalizationTab v-show="activeTab === 'localization'" data-tab="localization" :form="form" :locale-catalog="localeCatalog" />
                    <SeoTab v-show="activeTab === 'seo'" data-tab="seo" :form="form" />
                    <UsersTab v-show="activeTab === 'users'" data-tab="users" :form="form" :roles="roles" />
                    <SecurityTab v-show="activeTab === 'security'" data-tab="security" :form="form" />
                    <OauthTab v-show="activeTab === 'oauth'" data-tab="oauth" :form="form" :settings="settings" />
                    <LegalTab v-show="activeTab === 'legal'" data-tab="legal" />
                    <ContactTab v-show="activeTab === 'contact'" data-tab="contact" :form="form" />
                    <CaptchaTab v-show="activeTab === 'captcha'" data-tab="captcha" :form="form" :settings="settings" />
                    <ExtensionsTab v-show="activeTab === 'extensions'" data-tab="extensions" :extensions="extensionSettings" />
                </div>
            </div>

            <!-- Sticky save bar — hidden on tabs with no form fields -->
            <div v-show="!LINK_ONLY_TABS.includes(activeTab)" class="sticky bottom-0 mt-6 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-admin-bg/90 backdrop-blur border-t border-zinc-800/70 flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-blue-500 text-white font-semibold rounded-lg px-5 py-2 text-sm hover:bg-blue-400 transition-colors disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving…' : 'Save Settings' }}
                </button>
                <p v-if="form.recentlySuccessful" class="text-emerald-400 text-sm" role="status">Saved successfully.</p>
                <p v-if="Object.keys(form.errors).length > 0" class="text-red-400 text-sm" role="alert">Please fix the errors above.</p>
            </div>
        </form>

    </AdminLayout>
</template>
