<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Paintbrush, CheckCircle2, Info, Tag, User, Calendar, FolderOpen, ChevronLeft, KeyRound } from '@lucide/vue';
import { computed } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface ThemeSettingField {
    key: string;
    type: 'color' | 'text' | 'textarea' | 'toggle' | 'select' | 'image';
    label: string;
    group?: string;
    default: string | boolean;
    options?: string[];
}

interface ThemeDetail {
    id: number;
    name: string;
    slug: string;
    version: string;
    author: string;
    description: string;
    type: string;
    path: string;
    active: boolean;
    preview_image_url: string | null;
    metadata: Record<string, unknown> | null;
    installed_at: string | null;
    activated_at: string | null;
    settings_schema: ThemeSettingField[];
    settings: Record<string, string | boolean>;
    has_license: boolean;
    requires_license: boolean;
}

const props = defineProps<{ theme: ThemeDetail }>();

const typeConfig: Record<string, { label: string; cls: string }> = {
    official:  { label: 'Official',  cls: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
    community: { label: 'Community', cls: 'bg-violet-500/10 text-violet-400 border-violet-500/20' },
    custom:    { label: 'Custom',    cls: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
};
function typeConf(type: string) {
    return typeConfig[type] ?? typeConfig.custom;
}

function activate() { router.post(route('admin.themes.activate', props.theme.id)); }
function deactivate() { router.post(route('admin.themes.deactivate', props.theme.id)); }

// ── Settings ─────────────────────────────────────────────────────────────────

const groupedFields = computed(() => {
    const groups: Record<string, ThemeSettingField[]> = {};
    for (const field of props.theme.settings_schema) {
        const group = field.group || 'General';
        (groups[group] ??= []).push(field);
    }
    return groups;
});

const settingsForm = useForm<Record<string, string | boolean>>(
    Object.fromEntries(
        props.theme.settings_schema.map((f) => [f.key, props.theme.settings[f.key] ?? f.default]),
    ),
);

function submitSettings() {
    settingsForm.post(route('admin.themes.settings', props.theme.id), { preserveScroll: true });
}

// ── License ──────────────────────────────────────────────────────────────────
//
// A paid theme's key is never sent back to the browser — the field starts
// empty even when one is already saved. Same shape as Extensions/Show.vue.

const licenseForm = useForm({ license_key: '' });

function saveLicense() {
    licenseForm.post(route('admin.themes.license', props.theme.id), {
        preserveScroll: true,
        onSuccess: () => licenseForm.reset(),
    });
}

function removeLicense() {
    if (!confirm('Remove the stored license key? The theme cannot be activated without one.')) return;
    licenseForm.license_key = '';
    saveLicense();
}
</script>

<template>
    <Head :title="theme.name" />
    <AdminLayout :title="theme.name">

        <PageHeader :title="theme.name" description="Theme details and activation." :icon="Paintbrush">
            <template #actions>
                <Link
                    :href="route('admin.themes.index')"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors"
                >
                    <ChevronLeft :size="12" :stroke-width="2" />
                    All Themes
                </Link>

                <button
                    v-if="!theme.active"
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                    @click="activate"
                >
                    <CheckCircle2 :size="12" :stroke-width="2" />
                    Activate
                </button>
                <button
                    v-else
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-500 hover:text-zinc-300 hover:border-zinc-600 transition-colors"
                    @click="deactivate"
                >
                    Deactivate
                </button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_260px] gap-5 items-start">

            <!-- Left: preview + description -->
            <div class="flex flex-col gap-4">

                <!-- Preview card -->
                <div
                    class="bg-[#111113] border rounded-xl overflow-hidden"
                    :class="theme.active ? 'border-blue-500/40' : 'border-zinc-800/70'"
                >
                    <div class="w-full h-48 bg-zinc-900/60 border-b border-zinc-800/70 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="theme.preview_image_url"
                            :src="theme.preview_image_url"
                            :alt="theme.name"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="flex flex-col items-center gap-2 text-zinc-700">
                            <Paintbrush :size="32" :stroke-width="1.25" />
                            <span class="text-xs">No preview image</span>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="flex items-center gap-2 flex-wrap mb-2">
                            <span class="text-zinc-100 font-semibold text-sm">{{ theme.name }}</span>
                            <span
                                v-if="theme.active"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                            >
                                <CheckCircle2 :size="9" :stroke-width="2.5" />
                                Active
                            </span>
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                                :class="typeConf(theme.type).cls"
                            >{{ typeConf(theme.type).label }}</span>
                        </div>
                        <p class="text-zinc-600 text-xs font-mono">{{ theme.slug }}</p>
                        <p v-if="theme.description" class="text-zinc-400 text-sm mt-3 leading-relaxed">{{ theme.description }}</p>
                    </div>

                    <div v-if="theme.active" class="px-4 py-2 border-t border-blue-500/20 bg-blue-500/5 text-[10px] text-blue-400/70 font-medium">
                        This theme is currently active and applied to the public website.
                    </div>
                </div>

                <!-- License — only for themes that require or already have one -->
                <div
                    v-if="theme.requires_license || theme.has_license"
                    class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5"
                >
                    <div class="flex items-center gap-2 mb-1">
                        <KeyRound :size="14" :stroke-width="1.75" class="text-zinc-600" />
                        <h3 class="text-zinc-100 text-sm font-semibold">License</h3>
                        <span
                            v-if="theme.has_license"
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                        >
                            <CheckCircle2 :size="10" :stroke-width="2" />
                            Active
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-amber-500/10 text-amber-400 border-amber-500/20"
                        >Required</span>
                    </div>

                    <p class="text-zinc-500 text-xs leading-relaxed mb-3">
                        This is a paid theme. Enter the key you received with your purchase — the
                        theme cannot be activated without one.
                    </p>

                    <form class="flex flex-col sm:flex-row gap-2" @submit.prevent="saveLicense">
                        <input
                            v-model="licenseForm.license_key"
                            type="password"
                            autocomplete="off"
                            :placeholder="theme.has_license ? 'A key is stored — enter a new one to replace it' : 'Paste your license key'"
                            class="flex-1 min-w-0 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 font-mono placeholder:font-sans placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50"
                        />
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="licenseForm.processing || !licenseForm.license_key"
                                class="px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >Save</button>
                            <button
                                v-if="theme.has_license"
                                type="button"
                                :disabled="licenseForm.processing"
                                class="px-3 py-2 rounded-lg text-xs font-semibold border border-red-500/30 text-red-400 hover:bg-red-500/10 disabled:opacity-40 transition-colors"
                                @click="removeLicense"
                            >Remove</button>
                        </div>
                    </form>

                    <p v-if="licenseForm.errors.license_key" class="text-red-400 text-xs mt-2">
                        {{ licenseForm.errors.license_key }}
                    </p>
                </div>

                <!-- Settings -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-3">Theme Settings</h3>

                    <div v-if="theme.settings_schema.length === 0" class="flex items-start gap-3 bg-blue-500/5 border border-blue-500/20 rounded-lg px-3 py-3">
                        <Info :size="14" :stroke-width="1.75" class="text-blue-400 mt-0.5 shrink-0" />
                        <p class="text-zinc-400 text-xs leading-relaxed">
                            This theme has no configurable settings.
                        </p>
                    </div>

                    <form v-else class="flex flex-col gap-5" @submit.prevent="submitSettings">
                        <div v-for="(fields, group) in groupedFields" :key="group">
                            <p class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wide mb-2">{{ group }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="field in fields" :key="field.key" class="flex flex-col gap-1">
                                    <span class="text-xs text-zinc-400">{{ field.label }}</span>

                                    <div v-if="field.type === 'color'" class="flex items-center gap-2">
                                        <input
                                            v-model="settingsForm[field.key]"
                                            type="color"
                                            class="w-9 h-9 rounded-lg border border-zinc-800 bg-zinc-900 cursor-pointer"
                                        />
                                        <input
                                            v-model="settingsForm[field.key]"
                                            type="text"
                                            class="flex-1 min-w-0 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs font-mono text-zinc-200 focus:outline-none focus:border-blue-500/50"
                                        />
                                    </div>

                                    <select
                                        v-else-if="field.type === 'select'"
                                        v-model="settingsForm[field.key]"
                                        class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50"
                                    >
                                        <option v-for="opt in field.options ?? []" :key="opt" :value="opt">{{ opt }}</option>
                                    </select>

                                    <div v-else-if="field.type === 'toggle'" class="flex items-center gap-2 text-xs text-zinc-400">
                                        <input v-model="settingsForm[field.key]" type="checkbox" class="rounded border-zinc-700" />
                                        Enabled
                                    </div>

                                    <textarea
                                        v-else-if="field.type === 'textarea'"
                                        v-model="settingsForm[field.key]"
                                        rows="3"
                                        class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50"
                                    ></textarea>

                                    <input
                                        v-else
                                        v-model="settingsForm[field.key]"
                                        type="text"
                                        class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 focus:outline-none focus:border-blue-500/50"
                                    />

                                    <p v-if="settingsForm.errors[field.key]" class="text-red-400 text-[11px]">
                                        {{ settingsForm.errors[field.key] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button
                                type="submit"
                                :disabled="settingsForm.processing"
                                class="px-4 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 transition-colors"
                            >Save Settings</button>
                            <span v-if="settingsForm.recentlySuccessful" class="text-emerald-400 text-xs ml-3">Saved.</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-4">

                <!-- Details -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <Tag :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Details</span>
                    </div>
                    <div class="divide-y divide-zinc-800/40">
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Tag :size="11" :stroke-width="1.75" />
                                Version
                            </span>
                            <span class="text-xs font-mono text-zinc-300">v{{ theme.version }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <User :size="11" :stroke-width="1.75" />
                                Author
                            </span>
                            <span class="text-xs text-zinc-300">{{ theme.author }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Paintbrush :size="11" :stroke-width="1.75" />
                                Type
                            </span>
                            <span
                                class="text-[10px] font-semibold px-1.5 py-0.5 rounded border"
                                :class="typeConf(theme.type).cls"
                            >{{ typeConf(theme.type).label }}</span>
                        </div>
                        <div v-if="theme.installed_at" class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Calendar :size="11" :stroke-width="1.75" />
                                Installed
                            </span>
                            <span class="text-xs text-zinc-400">{{ theme.installed_at }}</span>
                        </div>
                        <div v-if="theme.activated_at" class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Calendar :size="11" :stroke-width="1.75" />
                                Last activated
                            </span>
                            <span class="text-xs text-zinc-400">{{ theme.activated_at }}</span>
                        </div>
                    </div>
                </div>

                <!-- Path -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <FolderOpen :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Path</span>
                    </div>
                    <div class="px-4 py-3">
                        <code class="text-xs font-mono text-zinc-400 break-all">themes/{{ theme.path }}</code>
                    </div>
                </div>

            </div>
        </div>

    </AdminLayout>
</template>
