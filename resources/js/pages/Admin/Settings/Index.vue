<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Settings, Globe, Search, Users, ShieldCheck, Puzzle, WrenchIcon, ExternalLink, Scale, ArrowRight, Mail, Shield, Send, CheckCircle2, AlertCircle } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { Link } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';

interface Props {
    settings: {
        app_name: string;
        app_url: string;
        default_locale: string;
        timezone: string;
        maintenance_mode: boolean;
        active_theme: string;
        seo_site_title: string;
        seo_meta_description: string;
        seo_og_image: string;
        social_discord: string;
        social_steam: string;
        social_twitter: string;
        social_youtube: string;
        registration_enabled: boolean;
        email_verification_required: boolean;
        default_user_role: string;
        oauth_enabled: boolean;
        oauth_discord_enabled: boolean;
        oauth_discord_client_id: string;
        oauth_discord_client_secret_set: boolean;
        oauth_steam_enabled: boolean;
        oauth_steam_client_secret_set: boolean;
        oauth_google_enabled: boolean;
        oauth_google_client_id: string;
        oauth_google_client_secret_set: boolean;
        password_min_length: number;
        password_require_mixed: boolean;
        password_require_numbers: boolean;
        loc_default_locale: string;
        loc_fallback_locale: string;
        loc_supported_locales: string;
        loc_public_switcher: boolean;
        loc_admin_switcher: boolean;
        avatar_enabled: boolean;
        avatar_max_kb: number;
        banner_enabled: boolean;
        banner_max_kb: number;
        username_change_cooldown_days: number;
        dm_enabled: boolean;
        dm_daily_limit: number;
        dm_max_length: number;
        notif_retention_days: number;
        contact_recipient_email: string;
        captcha_provider: string;
        captcha_turnstile_site_key: string;
        captcha_turnstile_secret_key_set: boolean;
        captcha_hcaptcha_site_key: string;
        captcha_hcaptcha_secret_key_set: boolean;
        captcha_recaptcha_v2_site_key: string;
        captcha_recaptcha_v2_secret_key_set: boolean;
        captcha_recaptcha_v3_site_key: string;
        captcha_recaptcha_v3_secret_key_set: boolean;
    };
    localeCatalog: Record<string, { name: string; native_name: string }>;
    locales: Record<string, string>;
    timezones: string[];
    extensionSettings: { slug: string; label: string; url: string; permission: string | null }[];
}

const props = defineProps<Props>();

const activeTab = ref('general');

const tabs = [
    { id: 'general',      label: 'General',      icon: Settings },
    { id: 'localization', label: 'Localization',  icon: Globe },
    { id: 'seo',          label: 'SEO',           icon: Search },
    { id: 'users',        label: 'Users & Access', icon: Users },
    { id: 'security',     label: 'Security',      icon: ShieldCheck },
    { id: 'oauth',        label: 'OAuth',         icon: Puzzle },
    { id: 'legal',        label: 'Legal',         icon: Scale },
    { id: 'contact',      label: 'Contact',       icon: Mail },
    { id: 'captcha',      label: 'Captcha',       icon: Shield },
    { id: 'extensions',   label: 'Extensions',    icon: WrenchIcon },
];

const form = useForm({
    app_name:                    props.settings.app_name,
    app_url:                     props.settings.app_url,
    default_locale:              props.settings.default_locale,
    timezone:                    props.settings.timezone,
    maintenance_mode:            props.settings.maintenance_mode,
    active_theme:                props.settings.active_theme,
    seo_site_title:              props.settings.seo_site_title ?? '',
    seo_meta_description:        props.settings.seo_meta_description ?? '',
    seo_og_image:                props.settings.seo_og_image ?? '',
    social_discord:              props.settings.social_discord ?? '',
    social_steam:                props.settings.social_steam ?? '',
    social_twitter:              props.settings.social_twitter ?? '',
    social_youtube:              props.settings.social_youtube ?? '',
    registration_enabled:        props.settings.registration_enabled ?? true,
    email_verification_required: props.settings.email_verification_required ?? false,
    default_user_role:           props.settings.default_user_role ?? 'member',
    oauth_enabled:               props.settings.oauth_enabled ?? false,
    oauth_discord_enabled:       props.settings.oauth_discord_enabled ?? false,
    oauth_discord_client_id:     props.settings.oauth_discord_client_id ?? '',
    oauth_discord_client_secret: '',
    oauth_steam_enabled:         props.settings.oauth_steam_enabled ?? false,
    oauth_steam_client_secret:   '',
    oauth_google_enabled:        props.settings.oauth_google_enabled ?? false,
    oauth_google_client_id:      props.settings.oauth_google_client_id ?? '',
    oauth_google_client_secret:  '',
    password_min_length:         props.settings.password_min_length ?? 8,
    password_require_mixed:      props.settings.password_require_mixed ?? false,
    password_require_numbers:    props.settings.password_require_numbers ?? true,
    loc_default_locale:          props.settings.loc_default_locale ?? 'en',
    loc_fallback_locale:         props.settings.loc_fallback_locale ?? 'en',
    loc_supported_locales:       props.settings.loc_supported_locales ?? 'en,bg',
    loc_public_switcher:                props.settings.loc_public_switcher ?? true,
    loc_admin_switcher:                 props.settings.loc_admin_switcher ?? true,
    avatar_enabled:                     props.settings.avatar_enabled ?? true,
    avatar_max_kb:                      props.settings.avatar_max_kb ?? 2048,
    banner_enabled:                     props.settings.banner_enabled ?? true,
    banner_max_kb:                      props.settings.banner_max_kb ?? 4096,
    username_change_cooldown_days:      props.settings.username_change_cooldown_days ?? 30,
    dm_enabled:                         props.settings.dm_enabled ?? true,
    dm_daily_limit:                     props.settings.dm_daily_limit ?? 100,
    dm_max_length:                      props.settings.dm_max_length ?? 2000,
    notif_retention_days:               props.settings.notif_retention_days ?? 90,
    contact_recipient_email:            props.settings.contact_recipient_email ?? '',
    captcha_provider:                   props.settings.captcha_provider ?? 'none',
    captcha_turnstile_site_key:         props.settings.captcha_turnstile_site_key ?? '',
    captcha_turnstile_secret_key:       '',
    captcha_hcaptcha_site_key:          props.settings.captcha_hcaptcha_site_key ?? '',
    captcha_hcaptcha_secret_key:        '',
    captcha_recaptcha_v2_site_key:      props.settings.captcha_recaptcha_v2_site_key ?? '',
    captcha_recaptcha_v2_secret_key:    '',
    captcha_recaptcha_v3_site_key:      props.settings.captcha_recaptcha_v3_site_key ?? '',
    captcha_recaptcha_v3_secret_key:    '',
});

function submit() {
    form.put(route('admin.settings.update'));
}

const inputClass = 'w-full bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20 placeholder:text-zinc-600';

// Test email
const testEmailAddress = ref('');
const testEmailSending = ref(false);

function sendTestEmail() {
    if (!testEmailAddress.value) return;
    testEmailSending.value = true;
    router.post(route('admin.settings.test-email'), { email: testEmailAddress.value }, {
        onFinish: () => { testEmailSending.value = false; },
    });
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
        pane.querySelectorAll('label').forEach((labelEl) => {
            const text = labelEl.textContent?.trim() ?? '';
            if (text && text.toLowerCase().includes(query)) {
                results.push({ tab, tabLabel, label: text.split('—')[0].trim() });
            }
        });
    });
    searchResults.value = results.slice(0, 12);
});

function goToSetting(result: { tab: string; label: string }) {
    activeTab.value = result.tab;
    settingsSearch.value = '';
    searchResults.value = [];
    nextTick(() => {
        const pane = document.querySelector(`[data-tab="${result.tab}"]`);
        const target = Array.from(pane?.querySelectorAll('label') ?? [])
            .find((l) => (l.textContent ?? '').trim().startsWith(result.label));
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('!text-blue-400');
        setTimeout(() => target.classList.remove('!text-blue-400'), 2500);
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
                type="text"
                placeholder="Search settings… (e.g. avatar, locale, captcha)"
                class="w-full pl-8 pr-3 py-2 rounded-xl border border-zinc-800 bg-zinc-900/60 text-zinc-100 text-[13px] placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 transition"
            />
            <div
                v-if="searchResults.length"
                class="absolute z-30 mt-1.5 w-full bg-[#16161a] border border-zinc-800 rounded-xl shadow-xl overflow-hidden"
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
                class="absolute z-30 mt-1.5 w-full bg-[#16161a] border border-zinc-800 rounded-xl shadow-xl px-3.5 py-2.5"
            >
                <span class="text-[12px] text-zinc-600">No settings match "{{ settingsSearch }}".</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="flex gap-5 items-start">

                <!-- Vertical tab sidebar -->
                <div class="w-44 shrink-0 bg-[#111113] border border-zinc-800/70 rounded-xl p-1.5 sticky top-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
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

                <!-- Content area — takes full remaining width -->
                <div class="flex-1 min-w-0">

            <!-- General -->
            <div v-show="activeTab === 'general'" data-tab="general" class="flex flex-col gap-4">

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Application</h3>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Application name</label>
                            <input v-model="form.app_name" type="text" :class="[inputClass, form.errors.app_name ? 'border-red-500' : '']" placeholder="My Platform" />
                            <p class="text-zinc-600 text-xs">Shown in the browser title bar, emails, and the admin panel header.</p>
                            <p v-if="form.errors.app_name" class="text-red-400 text-xs">{{ form.errors.app_name }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Application URL</label>
                            <input v-model="form.app_url" type="url" :class="[inputClass, form.errors.app_url ? 'border-red-500' : '']" placeholder="https://example.com" />
                            <p class="text-zinc-600 text-xs">The public-facing URL. Used for generating links in emails and OAuth callbacks.</p>
                            <p v-if="form.errors.app_url" class="text-red-400 text-xs">{{ form.errors.app_url }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Default locale</label>
                            <select v-model="form.default_locale" :class="inputClass">
                                <option v-for="(label, code) in locales" :key="code" :value="code">{{ label }}</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Timezone</label>
                            <select v-model="form.timezone" :class="inputClass">
                                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Theme & Availability</h3>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Active theme</label>
                        <input v-model="form.active_theme" type="text" placeholder="hybridcore/default" :class="[inputClass, 'font-mono']" />
                        <p class="text-zinc-600 text-xs">Use the <a :href="route('admin.themes.index')" class="text-blue-400 hover:underline">Themes page</a> to activate themes visually.</p>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Maintenance mode</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Hides public-facing pages from non-admin visitors.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.maintenance_mode ? 'bg-[#f59e0b]' : 'bg-zinc-500'"
                            @click="form.maintenance_mode = !form.maintenance_mode"
                        >
                            <span
                                class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform"
                                :class="form.maintenance_mode ? 'translate-x-4' : 'translate-x-0.5'"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Localization -->
            <div v-show="activeTab === 'localization'" data-tab="localization" class="flex flex-col gap-4">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Language Settings</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Default locale</label>
                            <select v-model="form.loc_default_locale" :class="inputClass">
                                <option v-for="(meta, code) in localeCatalog" :key="code" :value="code">
                                    {{ meta.native_name }} ({{ code }})
                                </option>
                            </select>
                            <p v-if="form.errors.loc_default_locale" class="text-red-400 text-xs">{{ form.errors.loc_default_locale }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Fallback locale</label>
                            <select v-model="form.loc_fallback_locale" :class="inputClass">
                                <option v-for="(meta, code) in localeCatalog" :key="code" :value="code">
                                    {{ meta.native_name }} ({{ code }})
                                </option>
                            </select>
                            <p class="text-zinc-600 text-xs">Used when a string is missing in the default locale.</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Supported locales</label>
                        <input v-model="form.loc_supported_locales" type="text" placeholder="en,bg" :class="[inputClass, 'font-mono']" />
                        <p class="text-zinc-600 text-xs">
                            Comma-separated codes (e.g. <code class="text-blue-400">en,bg</code>).
                            Available: {{ Object.keys(localeCatalog).join(', ') }}
                        </p>
                        <p v-if="form.errors.loc_supported_locales" class="text-red-400 text-xs">{{ form.errors.loc_supported_locales }}</p>
                    </div>
                </div>

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Language Switcher</h3>

                    <div class="flex items-center justify-between py-2">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Public language switcher</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Show a language picker on the public-facing website.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.loc_public_switcher ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.loc_public_switcher = !form.loc_public_switcher"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.loc_public_switcher ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-2 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Admin language switcher</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Show a language picker in the admin panel topbar.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.loc_admin_switcher ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.loc_admin_switcher = !form.loc_admin_switcher"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.loc_admin_switcher ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- SEO -->
            <div v-show="activeTab === 'seo'" data-tab="seo" class="flex flex-col gap-4">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Search Engine Optimization</h3>
                    <p class="text-zinc-600 text-xs -mt-2">These values are used as fallbacks when a page doesn't define its own metadata.</p>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Site title <span class="text-zinc-600 font-normal">— appended to page titles</span></label>
                        <input v-model="form.seo_site_title" type="text" placeholder="My Platform" :class="inputClass" />
                        <p class="text-zinc-600 text-xs">Produces titles like "Blog — My Platform".</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Default meta description</label>
                        <textarea v-model="form.seo_meta_description" rows="3" placeholder="A short description of your site shown in search results." :class="[inputClass, 'resize-none']" />
                        <p class="text-zinc-600 text-xs">Aim for 150–160 characters.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Default OG image URL</label>
                        <input v-model="form.seo_og_image" type="url" placeholder="https://example.com/og.png" :class="inputClass" />
                        <p class="text-zinc-600 text-xs">Shown when pages are shared on social media. Recommended size: 1200×630 px.</p>
                    </div>
                </div>

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Social links</h3>
                    <p class="text-zinc-600 text-xs -mt-2">Shown in the site header, footer and quick links. Leave a field empty to hide that platform.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Discord invite URL</label>
                            <input v-model="form.social_discord" type="url" placeholder="https://discord.gg/..." :class="inputClass" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Steam group URL</label>
                            <input v-model="form.social_steam" type="url" placeholder="https://steamcommunity.com/groups/..." :class="inputClass" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">X / Twitter URL</label>
                            <input v-model="form.social_twitter" type="url" placeholder="https://x.com/..." :class="inputClass" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">YouTube URL</label>
                            <input v-model="form.social_youtube" type="url" placeholder="https://youtube.com/@..." :class="inputClass" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users & Access -->
            <div v-show="activeTab === 'users'" data-tab="users" class="flex flex-col gap-4">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Registration</h3>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Allow public registration</p>
                            <p class="text-zinc-600 text-xs mt-0.5">New visitors can create an account on the public site.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.registration_enabled ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.registration_enabled = !form.registration_enabled"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.registration_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Require email verification</p>
                            <p class="text-zinc-600 text-xs mt-0.5">New accounts must verify their email before accessing the site.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.email_verification_required ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.email_verification_required = !form.email_verification_required"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.email_verification_required ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>
                </div>

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Defaults</h3>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Default user role</label>
                        <input v-model="form.default_user_role" type="text" :class="[inputClass, 'font-mono max-w-[200px]']" placeholder="member" />
                        <p class="text-zinc-600 text-xs">Role slug assigned to new users automatically on registration.</p>
                    </div>
                    <div class="flex flex-col gap-1.5 pt-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Username change cooldown (days)</label>
                        <input v-model.number="form.username_change_cooldown_days" type="number" min="0" max="365" class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500" />
                        <p class="text-zinc-600 text-xs">How many days users must wait before changing their username again. 0 = no limit.</p>
                    </div>
                </div>

                <!-- Avatars & Banners -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Avatars & Banners</h3>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Allow avatar uploads</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Users can upload a custom profile picture.</p>
                        </div>
                        <button type="button" class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.avatar_enabled ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.avatar_enabled = !form.avatar_enabled">
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.avatar_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Max avatar size (KB)</label>
                        <input v-model.number="form.avatar_max_kb" type="number" min="128" max="10240" :disabled="!form.avatar_enabled"
                            class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500 disabled:opacity-40" />
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Allow banner uploads</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Users can upload a profile banner image (1200×300).</p>
                        </div>
                        <button type="button" class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.banner_enabled ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.banner_enabled = !form.banner_enabled">
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.banner_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Max banner size (KB)</label>
                        <input v-model.number="form.banner_max_kb" type="number" min="512" max="20480" :disabled="!form.banner_enabled"
                            class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500 disabled:opacity-40" />
                    </div>
                </div>

                <!-- Private Messages -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Private Messages</h3>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Enable DMs</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Allow members to send direct messages to each other.</p>
                        </div>
                        <button type="button" class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.dm_enabled ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.dm_enabled = !form.dm_enabled">
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.dm_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Daily message limit per user</label>
                        <input v-model.number="form.dm_daily_limit" type="number" min="1" max="10000" :disabled="!form.dm_enabled"
                            class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500 disabled:opacity-40" />
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Max message length (chars)</label>
                        <input v-model.number="form.dm_max_length" type="number" min="100" max="10000" :disabled="!form.dm_enabled"
                            class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500 disabled:opacity-40" />
                    </div>
                </div>

                <!-- Notifications -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Notifications</h3>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Retain notifications for (days)</label>
                        <div class="flex items-center gap-2">
                            <input v-model.number="form.notif_retention_days" type="number" min="7" max="365"
                                class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:border-blue-500" />
                            <p class="text-zinc-600 text-xs">days</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security -->
            <div v-show="activeTab === 'security'" data-tab="security" class="flex flex-col gap-4">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4">
                    <h3 class="text-zinc-100 text-sm font-semibold">Password Requirements</h3>
                    <p class="text-zinc-600 text-xs -mt-2">Applied when users register or change their password.</p>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-zinc-400 text-xs font-medium">Minimum length</label>
                        <div class="flex items-center gap-3">
                            <input
                                v-model.number="form.password_min_length"
                                type="number" min="8" max="128"
                                class="w-24 bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm text-center
                                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20"
                            />
                            <p class="text-zinc-600 text-xs">characters (min 8, max 128)</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Require mixed case</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Password must contain both uppercase and lowercase letters.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.password_require_mixed ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.password_require_mixed = !form.password_require_mixed"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.password_require_mixed ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-zinc-800/70">
                        <div>
                            <p class="text-zinc-100 text-sm font-medium">Require numbers</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Password must contain at least one digit.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.password_require_numbers ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.password_require_numbers = !form.password_require_numbers"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.password_require_numbers ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- OAuth -->
            <div v-show="activeTab === 'oauth'" data-tab="oauth" class="flex flex-col gap-4">

                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-zinc-100 text-sm font-semibold">Enable OAuth sign-in</p>
                            <p class="text-zinc-600 text-xs mt-0.5">Master switch — turns all OAuth providers on or off at once.</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.oauth_enabled ? 'bg-blue-500' : 'bg-zinc-500'"
                            @click="form.oauth_enabled = !form.oauth_enabled"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.oauth_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>
                </div>

                <!-- Discord -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4" :class="!form.oauth_enabled ? 'opacity-50 pointer-events-none' : ''">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-[#5865f2]/15 flex items-center justify-center">
                                <svg class="w-4 h-4 text-[#5865f2]" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057.1 18.08.11 18.103.129 18.117a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/></svg>
                            </span>
                            <p class="text-zinc-100 text-sm font-semibold">Discord</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.oauth_discord_enabled ? 'bg-[#5865f2]' : 'bg-zinc-500'"
                            @click="form.oauth_discord_enabled = !form.oauth_discord_enabled"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.oauth_discord_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1" :class="!form.oauth_discord_enabled ? 'opacity-40' : ''">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Client ID</label>
                            <input v-model="form.oauth_discord_client_id" type="text" :class="inputClass" :disabled="!form.oauth_discord_enabled" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">
                                Client Secret
                                <span v-if="settings.oauth_discord_client_secret_set" class="text-emerald-400 font-normal">— set</span>
                            </label>
                            <input v-model="form.oauth_discord_client_secret" type="password" autocomplete="new-password" :class="inputClass" :disabled="!form.oauth_discord_enabled"
                                   :placeholder="settings.oauth_discord_client_secret_set ? 'Leave blank to keep' : 'Enter secret'" />
                        </div>
                    </div>
                </div>

                <!-- Steam -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4" :class="!form.oauth_enabled ? 'opacity-50 pointer-events-none' : ''">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-[#1b2838]/80 border border-[#2a475e] flex items-center justify-center">
                                <svg class="w-4 h-4 text-[#c7d5e0]" viewBox="0 0 24 24" fill="currentColor"><path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.031 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.606 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.5 1.009 2.455-.397.957-1.497 1.41-2.455 1.012H7.54zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.662 0 3.015-1.35 3.015-3.015zm-5.273.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.252 0-2.265-1.014-2.265-2.265z"/></svg>
                            </span>
                            <p class="text-zinc-100 text-sm font-semibold">Steam</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.oauth_steam_enabled ? 'bg-[#4c6b22]' : 'bg-zinc-500'"
                            @click="form.oauth_steam_enabled = !form.oauth_steam_enabled"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.oauth_steam_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="pt-1" :class="!form.oauth_steam_enabled ? 'opacity-40' : ''">
                        <div class="flex flex-col gap-1.5 max-w-sm">
                            <label class="text-zinc-400 text-xs font-medium">
                                Steam Web API Key
                                <span v-if="settings.oauth_steam_client_secret_set" class="text-emerald-400 font-normal">— set</span>
                            </label>
                            <input v-model="form.oauth_steam_client_secret" type="password" autocomplete="new-password" :class="inputClass" :disabled="!form.oauth_steam_enabled"
                                   :placeholder="settings.oauth_steam_client_secret_set ? 'Leave blank to keep' : 'Enter API key'" />
                        </div>
                        <p class="text-zinc-600 text-xs mt-2">Steam doesn't provide an email — new accounts will be prompted to add one after signing in.</p>
                    </div>
                </div>

                <!-- Google -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-4" :class="!form.oauth_enabled ? 'opacity-50 pointer-events-none' : ''">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-white/90 border border-zinc-300/40 flex items-center justify-center">
                                <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.34-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.77.43 3.45 1.18 4.94l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38z"/></svg>
                            </span>
                            <p class="text-zinc-100 text-sm font-semibold">Google</p>
                        </div>
                        <button
                            type="button"
                            class="relative w-10 h-6 rounded-full transition-colors shrink-0 overflow-hidden"
                            :class="form.oauth_google_enabled ? 'bg-[#4285F4]' : 'bg-zinc-500'"
                            @click="form.oauth_google_enabled = !form.oauth_google_enabled"
                        >
                            <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow-sm transition-transform" :class="form.oauth_google_enabled ? 'translate-x-4' : 'translate-x-0.5'" />
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1" :class="!form.oauth_google_enabled ? 'opacity-40' : ''">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">Client ID</label>
                            <input v-model="form.oauth_google_client_id" type="text" :class="inputClass" :disabled="!form.oauth_google_enabled" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-zinc-400 text-xs font-medium">
                                Client Secret
                                <span v-if="settings.oauth_google_client_secret_set" class="text-emerald-400 font-normal">— set</span>
                            </label>
                            <input v-model="form.oauth_google_client_secret" type="password" autocomplete="new-password" :class="inputClass" :disabled="!form.oauth_google_enabled"
                                   :placeholder="settings.oauth_google_client_secret_set ? 'Leave blank to keep' : 'Enter secret'" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legal Pages -->
            <div v-show="activeTab === 'legal'" data-tab="legal" class="max-w-lg">
                <Link
                    :href="route('admin.legal.index')"
                    class="flex items-center justify-between px-5 py-4 bg-[#111113] border border-zinc-800/70 rounded-xl hover:border-blue-500/40 hover:bg-blue-500/5 transition-colors group"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-zinc-800/60 border border-zinc-700/50 flex items-center justify-center shrink-0">
                            <Scale :size="15" :stroke-width="1.75" class="text-zinc-400" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-zinc-200 group-hover:text-white transition-colors">Manage Legal Pages</p>
                            <p class="text-xs text-zinc-600 mt-0.5">Create and edit Terms, Privacy, Cookies and custom legal pages</p>
                        </div>
                    </div>
                    <ArrowRight :size="15" :stroke-width="1.75" class="text-zinc-600 group-hover:text-blue-400 transition-colors shrink-0" />
                </Link>
            </div>

            <!-- Contact -->
            <div v-show="activeTab === 'contact'" data-tab="contact" class="flex flex-col gap-5">
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Contact Form</h3>

                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Recipient email</label>
                        <input
                            v-model="form.contact_recipient_email"
                            type="email"
                            placeholder="admin@example.com"
                            :class="[inputClass, form.errors.contact_recipient_email ? 'border-red-500' : '']"
                        />
                        <p class="text-zinc-600 text-xs">Where contact form submissions are sent via email.</p>
                        <p v-if="form.errors.contact_recipient_email" class="text-xs text-red-400">{{ form.errors.contact_recipient_email }}</p>
                    </div>
                </div>

                <!-- SMTP Test -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-1">Test Email Delivery</h3>
                    <p class="text-zinc-600 text-xs mb-4">Send a test email to verify your SMTP configuration is working correctly.</p>
                    <div class="flex gap-2">
                        <input
                            v-model="testEmailAddress"
                            type="email"
                            placeholder="you@example.com"
                            :class="inputClass"
                            @keyup.enter="sendTestEmail"
                        />
                        <button
                            type="button"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-500 transition-colors shrink-0 disabled:opacity-50"
                            :disabled="testEmailSending || !testEmailAddress"
                            @click="sendTestEmail"
                        >
                            <Send :size="13" :stroke-width="1.75" />
                            {{ testEmailSending ? 'Sending…' : 'Send Test' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Captcha -->
            <div v-show="activeTab === 'captcha'" data-tab="captcha" class="flex flex-col gap-5">
                <!-- Provider selector -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Captcha Provider</h3>

                    <div class="flex flex-col gap-2 py-3 border-t border-zinc-800/70">
                        <p class="text-zinc-600 text-xs mb-2">Select the captcha service to protect forms. Choose "None" to disable captcha entirely.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <button
                                v-for="opt in [
                                    { value: 'none',          label: 'None',            desc: 'No captcha' },
                                    { value: 'turnstile',     label: 'Cloudflare',      desc: 'Turnstile' },
                                    { value: 'hcaptcha',      label: 'hCaptcha',        desc: 'Privacy-first' },
                                    { value: 'recaptcha_v2',  label: 'reCAPTCHA v2',    desc: 'Google checkbox' },
                                    { value: 'recaptcha_v3',  label: 'reCAPTCHA v3',    desc: 'Google invisible' },
                                ]"
                                :key="opt.value"
                                type="button"
                                class="flex flex-col items-start px-4 py-3 rounded-xl border transition-colors text-left"
                                :class="form.captcha_provider === opt.value
                                    ? 'border-blue-500/60 bg-blue-500/10 text-blue-400'
                                    : 'border-zinc-800 text-zinc-500 hover:border-zinc-700 hover:text-zinc-400'"
                                @click="form.captcha_provider = opt.value"
                            >
                                <span class="text-xs font-semibold">{{ opt.label }}</span>
                                <span class="text-[11px] opacity-70">{{ opt.desc }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Turnstile -->
                <div v-if="form.captcha_provider === 'turnstile'" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Cloudflare Turnstile Keys</h3>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Site Key</label>
                        <input v-model="form.captcha_turnstile_site_key" type="text" placeholder="0x4AAAA…" :class="[inputClass, 'font-mono']" />
                    </div>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">
                            Secret Key
                            <span v-if="settings.captcha_turnstile_secret_key_set" class="text-emerald-400 font-normal">— set</span>
                        </label>
                        <input v-model="form.captcha_turnstile_secret_key" type="password" placeholder="Enter new secret key" :class="inputClass" />
                    </div>
                </div>

                <!-- hCaptcha -->
                <div v-if="form.captcha_provider === 'hcaptcha'" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">hCaptcha Keys</h3>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Site Key</label>
                        <input v-model="form.captcha_hcaptcha_site_key" type="text" placeholder="10000000-ffff-…" :class="[inputClass, 'font-mono']" />
                    </div>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">
                            Secret Key
                            <span v-if="settings.captcha_hcaptcha_secret_key_set" class="text-emerald-400 font-normal">— set</span>
                        </label>
                        <input v-model="form.captcha_hcaptcha_secret_key" type="password" placeholder="Enter new secret key" :class="inputClass" />
                    </div>
                </div>

                <!-- reCAPTCHA v2 -->
                <div v-if="form.captcha_provider === 'recaptcha_v2'" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Google reCAPTCHA v2 Keys</h3>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Site Key</label>
                        <input v-model="form.captcha_recaptcha_v2_site_key" type="text" placeholder="6LeIxAcT…" :class="[inputClass, 'font-mono']" />
                    </div>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">
                            Secret Key
                            <span v-if="settings.captcha_recaptcha_v2_secret_key_set" class="text-emerald-400 font-normal">— set</span>
                        </label>
                        <input v-model="form.captcha_recaptcha_v2_secret_key" type="password" placeholder="Enter new secret key" :class="inputClass" />
                    </div>
                </div>

                <!-- reCAPTCHA v3 -->
                <div v-if="form.captcha_provider === 'recaptcha_v3'" class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex flex-col gap-0">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-4">Google reCAPTCHA v3 Keys</h3>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">Site Key</label>
                        <input v-model="form.captcha_recaptcha_v3_site_key" type="text" placeholder="6LcA…" :class="[inputClass, 'font-mono']" />
                    </div>
                    <div class="flex flex-col gap-1.5 py-3 border-t border-zinc-800/70">
                        <label class="text-zinc-400 text-xs font-medium">
                            Secret Key
                            <span v-if="settings.captcha_recaptcha_v3_secret_key_set" class="text-emerald-400 font-normal">— set</span>
                        </label>
                        <input v-model="form.captcha_recaptcha_v3_secret_key" type="password" placeholder="Enter new secret key" :class="inputClass" />
                    </div>
                </div>
            </div>

            <!-- Extensions -->
            <div v-show="activeTab === 'extensions'" data-tab="extensions" class="">
                <div v-if="extensionSettings.length" class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-zinc-800/60 bg-[#1a1a1e]">
                        <h3 class="text-zinc-100 text-sm font-semibold">Installed Extension Settings</h3>
                        <p class="text-zinc-500 text-xs mt-0.5">Each enabled extension that provides settings has its own dedicated page.</p>
                    </div>
                    <div class="divide-y divide-zinc-800/50">
                        <a
                            v-for="ext in extensionSettings"
                            :key="ext.slug"
                            :href="ext.url"
                            class="flex items-center justify-between px-5 py-4 hover:bg-white/[0.02] transition-colors group"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-zinc-800 border border-zinc-700/60 flex items-center justify-center">
                                    <WrenchIcon :size="14" :stroke-width="1.75" class="text-zinc-400" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-zinc-200 group-hover:text-white transition-colors">{{ ext.label }}</p>
                                    <p class="text-xs text-zinc-600 font-mono">/admin/settings/extensions/{{ ext.slug }}</p>
                                </div>
                            </div>
                            <ExternalLink :size="13" :stroke-width="1.75" class="text-zinc-700 group-hover:text-zinc-400 transition-colors shrink-0" />
                        </a>
                    </div>
                </div>

                <div v-else class="bg-[#111113] border border-zinc-800/70 rounded-xl p-10 text-center">
                    <WrenchIcon :size="22" :stroke-width="1.5" class="mx-auto mb-2 text-zinc-700" />
                    <p class="text-zinc-500 text-sm">No extensions with settings are currently enabled.</p>
                </div>
            </div>

                </div> <!-- end content area -->
            </div> <!-- end flex row -->

            <!-- Sticky save bar — hidden on tabs with no form fields -->
            <div v-show="activeTab !== 'extensions' && activeTab !== 'legal'" class="sticky bottom-0 mt-6 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-[#09090b]/90 backdrop-blur border-t border-zinc-800/70 flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-blue-500 text-white font-semibold rounded-lg px-5 py-2 text-sm hover:bg-blue-400 transition-colors disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving…' : 'Save Settings' }}
                </button>
                <p v-if="form.recentlySuccessful" class="text-emerald-400 text-sm">Saved successfully.</p>
                <p v-if="Object.keys(form.errors).length > 0" class="text-red-400 text-sm">Please fix the errors above.</p>
            </div>

        </form>

    </AdminLayout>
</template>
