<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Puzzle, ToggleRight, ToggleLeft, Tag, User, Calendar, FolderOpen, Info, ChevronLeft, Trash2, KeyRound, CheckCircle2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface ExtensionDetail {
    id: number;
    name: string;
    slug: string;
    version: string;
    author: string;
    description: string;
    type: string;
    path: string;
    enabled: boolean;
    metadata: Record<string, unknown> | null;
    has_license: boolean;
    requires_license: boolean;
    update_url: string | null;
    installed_at: string | null;
    enabled_at: string | null;
    disabled_at: string | null;
}

const props = defineProps<{ extension: ExtensionDetail }>();

const typeConfig: Record<string, { label: string; cls: string }> = {
    official:  { label: 'Official',  cls: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
    community: { label: 'Community', cls: 'bg-violet-500/10 text-violet-400 border-violet-500/20' },
    custom:    { label: 'Custom',    cls: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
};
function typeConf(type: string) {
    return typeConfig[type] ?? typeConfig.custom;
}

function toggle() {
    router.post(route(props.extension.enabled ? 'admin.extensions.disable' : 'admin.extensions.enable', props.extension.id));
}

// A paid extension is fetched from a private repository, so it needs the key
// that came with the purchase. The stored value is never sent to the browser —
// the field starts empty even when one is saved.
const licenseForm = useForm({ license_key: '' });

function saveLicense() {
    licenseForm.post(route('admin.extensions.license', props.extension.id), {
        preserveScroll: true,
        onSuccess: () => licenseForm.reset(),
    });
}

function removeLicense() {
    if (!confirm('Remove the stored license key? Updates will stop until a new one is entered.')) return;
    licenseForm.license_key = '';
    saveLicense();
}

function uninstall() {
    if (!confirm(`Uninstall "${props.extension.name}"? Its files will be deleted.`)) return;
    const dropData = confirm('Also delete its database tables and data?\n\nOK = delete data permanently · Cancel = keep the data for a future reinstall');
    router.delete(route('admin.extensions.uninstall', props.extension.id), { data: { drop_data: dropData } });
}
</script>

<template>
    <Head :title="extension.name" />
    <AdminLayout :title="extension.name">

        <PageHeader :title="extension.name" description="Extension details and controls." :icon="Puzzle">
            <template #actions>
                <Link
                    :href="route('admin.extensions.index')"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors"
                >
                    <ChevronLeft :size="12" :stroke-width="2" />
                    All Extensions
                </Link>

                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                    :class="extension.enabled
                        ? 'border-red-500/30 text-red-400 hover:bg-red-500/10'
                        : 'bg-blue-500 text-white border-transparent hover:bg-blue-400'"
                    @click="toggle"
                >
                    <component :is="extension.enabled ? ToggleRight : ToggleLeft" :size="13" :stroke-width="2" />
                    {{ extension.enabled ? 'Disable' : 'Enable' }}
                </button>

                <button
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-500/30 bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors"
                    @click="uninstall"
                >
                    <Trash2 :size="12" :stroke-width="2" />
                    Uninstall
                </button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_260px] gap-5 items-start">

            <!-- Left: main info -->
            <div class="flex flex-col gap-4">

                <!-- Description card -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                            :class="extension.enabled ? 'bg-blue-500/10 border-blue-500/20' : 'bg-zinc-800 border-zinc-700'">
                            <Puzzle :size="18" :stroke-width="1.5" :class="extension.enabled ? 'text-blue-400' : 'text-zinc-600'" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-zinc-100 text-base font-semibold">{{ extension.name }}</h2>
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                                    :class="typeConf(extension.type).cls"
                                >{{ typeConf(extension.type).label }}</span>
                                <span
                                    class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                                    :class="extension.enabled
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                        : 'bg-zinc-800 text-zinc-500 border-zinc-700'"
                                >{{ extension.enabled ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                            <p class="text-zinc-600 text-xs font-mono mt-0.5">{{ extension.slug }}</p>
                        </div>
                    </div>

                    <p class="text-zinc-400 text-sm leading-relaxed">
                        {{ extension.description || 'No description provided.' }}
                    </p>
                </div>

                <!-- License — only for extensions that have an update channel -->
                <div
                    v-if="extension.requires_license || extension.has_license || extension.update_url"
                    class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5"
                >
                    <div class="flex items-center gap-2 mb-1">
                        <KeyRound :size="14" :stroke-width="1.75" class="text-zinc-600" />
                        <h3 class="text-zinc-100 text-sm font-semibold">License</h3>
                        <span
                            v-if="extension.has_license"
                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border-emerald-500/20"
                        >
                            <CheckCircle2 :size="10" :stroke-width="2" />
                            Active
                        </span>
                        <span
                            v-else-if="extension.requires_license"
                            class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold bg-amber-500/10 text-amber-400 border-amber-500/20"
                        >Required</span>
                    </div>

                    <p class="text-zinc-500 text-xs leading-relaxed mb-3">
                        {{ extension.requires_license
                            ? 'This is a paid extension. Enter the key you received with your purchase to receive updates.'
                            : 'Optional. Only needed if this extension is distributed from a private repository.' }}
                    </p>

                    <form class="flex flex-col sm:flex-row gap-2" @submit.prevent="saveLicense">
                        <input
                            v-model="licenseForm.license_key"
                            type="password"
                            autocomplete="off"
                            :placeholder="extension.has_license ? 'A key is stored — enter a new one to replace it' : 'Paste your license key'"
                            class="flex-1 min-w-0 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-xs text-zinc-200 font-mono placeholder:font-sans placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50"
                        />
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                :disabled="licenseForm.processing || !licenseForm.license_key"
                                class="px-3 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >Save</button>
                            <button
                                v-if="extension.has_license"
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

                <!-- Settings placeholder -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-3">Extension Settings</h3>
                    <div class="flex items-start gap-3 bg-blue-500/5 border border-blue-500/20 rounded-lg px-3 py-3">
                        <Info :size="14" :stroke-width="1.75" class="text-blue-400 mt-0.5 shrink-0" />
                        <p class="text-zinc-400 text-xs leading-relaxed">
                            Per-extension settings are available once the extension registers a settings schema.
                            This feature arrives in a future update.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-4">

                <!-- Meta -->
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
                            <span class="text-xs font-mono text-zinc-300">v{{ extension.version }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <User :size="11" :stroke-width="1.75" />
                                Author
                            </span>
                            <span class="text-xs text-zinc-300">{{ extension.author }}</span>
                        </div>
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Puzzle :size="11" :stroke-width="1.75" />
                                Type
                            </span>
                            <span
                                class="text-[10px] font-semibold px-1.5 py-0.5 rounded border"
                                :class="typeConf(extension.type).cls"
                            >{{ typeConf(extension.type).label }}</span>
                        </div>
                        <div v-if="extension.installed_at" class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Calendar :size="11" :stroke-width="1.75" />
                                Installed
                            </span>
                            <span class="text-xs text-zinc-400">{{ extension.installed_at }}</span>
                        </div>
                        <div v-if="extension.enabled_at" class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-xs text-zinc-500 flex items-center gap-1.5">
                                <Calendar :size="11" :stroke-width="1.75" />
                                Last enabled
                            </span>
                            <span class="text-xs text-zinc-400">{{ extension.enabled_at }}</span>
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
                        <code class="text-xs font-mono text-zinc-400 break-all">extensions/{{ extension.path }}</code>
                    </div>
                </div>

            </div>
        </div>

    </AdminLayout>
</template>
