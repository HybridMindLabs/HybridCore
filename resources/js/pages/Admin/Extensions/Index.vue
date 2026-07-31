<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Puzzle, RefreshCw, ToggleRight, ToggleLeft, Settings, CheckCircle2, XCircle, Loader2, AlertTriangle, Hammer, Upload, ChevronDown, ArrowUpCircle, KeyRound } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { computed, ref } from 'vue';

interface Extension {
    id: number;
    name: string;
    slug: string;
    version: string;
    author: string;
    description: string;
    type: 'official' | 'community' | 'custom';
    enabled: boolean;
    settings_url: string | null;
    installed_at: string | null;
    enabled_at: string | null;
    /** Present only when the extension's declared feed offers a newer release. */
    update: { version: string; url: string; notes: string } | null;
    requires_license: boolean;
    has_license: boolean;
}

interface RebuildInfo {
    status: 'pending' | 'building' | 'done' | 'failed';
    last_at: string | null;
}

const props = defineProps<{ extensions: Extension[]; rebuild: RebuildInfo }>();

const rebuilding = ref(false);

function triggerRebuild() {
    rebuilding.value = true;
    router.post(route('admin.extensions.rebuild'), {}, {
        onFinish: () => { rebuilding.value = false; },
    });
}

const syncing = ref(false);
const togglingId = ref<number | null>(null);

const summary = computed(() => ({
    total:    props.extensions.length,
    enabled:  props.extensions.filter(e => e.enabled).length,
    disabled: props.extensions.filter(e => !e.enabled).length,
}));

function sync() {
    syncing.value = true;
    router.post(route('admin.extensions.sync'), {}, {
        onFinish: () => { syncing.value = false; },
    });
}

const checkingUpdates = ref(false);

function checkUpdates() {
    checkingUpdates.value = true;
    // Bypasses the hourly cache server-side and re-renders with fresh badges.
    router.post(route('admin.extensions.check-updates'), {}, {
        preserveScroll: true,
        onFinish: () => { checkingUpdates.value = false; },
    });
}

// Which extension is mid-update, so only its own button shows the busy state.
const updating = ref<string | null>(null);

function applyUpdate(ext: Extension) {
    if (!ext.update) return;

    // Downloads and installs through the same validated import path an
    // uploaded archive takes, so migrations and assets are handled too.
    // preserveState keeps the current scroll position and selection, so the
    // row updates in place instead of feeling like a page change.
    updating.value = ext.slug;
    router.post(route('admin.extensions.update', { extension: ext.id }), {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { updating.value = null; },
    });
}

// ── Select-all for the table header ─────────────────────────
const allSelected = computed(() =>
    props.extensions.length > 0 && selected.value.length === props.extensions.length);

function toggleAll() {
    selected.value = allSelected.value ? [] : props.extensions.map((e) => e.id);
}

// ── Bulk actions ────────────────────────────────────────────
const selected = ref<number[]>([]);
const bulkMenuOpen = ref(false);
const bulkPending = ref(false);

function toggleOne(id: number) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((x) => x !== id)
        : [...selected.value, id];
}

function runBulk(action: 'enable' | 'disable') {
    bulkMenuOpen.value = false;
    if (selected.value.length === 0) return;
    if (!confirm(`${action} ${selected.value.length} selected extension(s)?`)) return;
    bulkPending.value = true;
    router.post(route('admin.extensions.bulk'), { action, extension_ids: selected.value }, {
        onFinish: () => { bulkPending.value = false; selected.value = []; },
    });
}

interface ImportPreview {
    token: string;
    is_update: boolean;
    current_version: string | null;
    manifest: {
        id: string | null; name: string; version: string; author: string | null;
        description: string | null; type: string;
        permissions: unknown[]; routes: Record<string, unknown>;
        migrations: string | null; provider: string | null;
    };
    warnings: string[];
}

const importing = ref(false);
const confirming = ref(false);
const importError = ref('');
const preview = ref<ImportPreview | null>(null);
const archiveInput = ref<HTMLInputElement | null>(null);

function pickArchive() {
    archiveInput.value?.click();
}

async function importArchive(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (! file) return;

    importError.value = '';
    importing.value = true;
    try {
        const fd = new FormData();
        fd.append('archive', file);
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        const res = await fetch(route('admin.extensions.import.preview'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const json = await res.json();
        if (!res.ok) {
            importError.value = json.error ?? 'Import failed.';
            return;
        }
        preview.value = json;
    } finally {
        importing.value = false;
        if (archiveInput.value) archiveInput.value.value = '';
    }
}

function confirmImport() {
    if (!preview.value) return;
    confirming.value = true;
    router.post(route('admin.extensions.import.confirm'), { token: preview.value.token }, {
        onFinish: () => { confirming.value = false; preview.value = null; },
    });
}

function cancelImport() {
    preview.value = null;
}

function toggle(ext: Extension) {
    togglingId.value = ext.id;
    router.post(route(ext.enabled ? 'admin.extensions.disable' : 'admin.extensions.enable', ext.id), {}, {
        onFinish: () => { togglingId.value = null; },
    });
}

const typeConfig = {
    official:  { label: 'Official',  cls: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
    community: { label: 'Community', cls: 'bg-violet-500/10 text-violet-400 border-violet-500/20' },
    custom:    { label: 'Custom',    cls: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
} as const;

function typeConf(type: string) {
    return typeConfig[type as keyof typeof typeConfig] ?? typeConfig.custom;
}
</script>

<template>
    <Head title="Extensions" />
    <AdminLayout title="Extensions">

        <PageHeader
            title="Extensions"
            description="Manage installed extensions and their settings."
            :icon="Puzzle"
        >
            <template #actions>
                <div v-if="selected.length > 0" class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 bg-zinc-800 text-zinc-200 hover:bg-zinc-700 transition-colors"
                        :disabled="bulkPending"
                        @click.stop="bulkMenuOpen = !bulkMenuOpen"
                    >
                        <span>{{ selected.length }} selected</span>
                        <ChevronDown :size="12" :stroke-width="2" :class="bulkMenuOpen ? 'rotate-180' : ''" class="transition-transform" />
                    </button>
                    <div v-if="bulkMenuOpen" class="absolute right-0 top-full mt-1 w-40 bg-zinc-900 border border-zinc-800 rounded-lg shadow-xl py-1 z-50">
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('enable')">
                            <ToggleRight :size="13" :stroke-width="1.75" class="text-emerald-400" /> Enable
                        </button>
                        <button type="button" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition-colors" @click="runBulk('disable')">
                            <ToggleLeft :size="13" :stroke-width="1.75" class="text-zinc-500" /> Disable
                        </button>
                    </div>
                </div>
                <input ref="archiveInput" type="file" accept=".zip" class="hidden" @change="importArchive" />
                <button
                    type="button"
                    :disabled="importing"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors disabled:opacity-50"
                    @click="pickArchive"
                >
                    <Upload :size="12" :stroke-width="2" :class="importing ? 'animate-pulse' : ''" />
                    {{ importing ? 'Reading…' : 'Import ZIP' }}
                </button>
                <button
                    type="button"
                    :disabled="rebuilding || rebuild.status === 'building' || rebuild.status === 'pending'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors disabled:opacity-50"
                    @click="triggerRebuild"
                >
                    <Hammer :size="12" :stroke-width="2" :class="rebuilding ? 'animate-pulse' : ''" />
                    Rebuild assets
                </button>
                <button
                    type="button"
                    :disabled="syncing"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors disabled:opacity-50"
                    @click="sync"
                >
                    <RefreshCw :size="12" :stroke-width="2" :class="syncing ? 'animate-spin' : ''" />
                    {{ syncing ? 'Syncing…' : 'Sync from disk' }}
                </button>
                <!-- Results are cached for an hour, so this is the way to see a
                     release the moment an author publishes it. -->
                <button
                    type="button"
                    :disabled="checkingUpdates"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors disabled:opacity-50"
                    @click="checkUpdates"
                >
                    <ArrowUpCircle :size="12" :stroke-width="2" :class="checkingUpdates ? 'animate-pulse' : ''" />
                    {{ checkingUpdates ? 'Checking…' : 'Check for updates' }}
                </button>
            </template>
        </PageHeader>

        <!-- Import error -->
        <div v-if="importError" class="mb-4 flex items-center gap-3 rounded-xl border border-red-500/20 bg-red-500/5 px-4 py-3 text-[13px]">
            <AlertTriangle :size="14" :stroke-width="2" class="text-red-400 shrink-0" />
            <span class="text-red-300">{{ importError }}</span>
        </div>

        <!-- Import preview modal -->
        <div v-if="preview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4" @click.self="cancelImport">
            <div class="w-full max-w-lg bg-[#111113] border border-zinc-800/70 rounded-2xl overflow-hidden shadow-2xl">
                <div class="px-5 py-4 border-b border-zinc-800/60 bg-[#1a1a1e]">
                    <h2 class="text-sm font-bold text-zinc-100">Review before installing</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">Nothing has been extracted yet — confirm the details below.</p>
                </div>
                <div class="p-5 flex flex-col gap-3 max-h-[60vh] overflow-y-auto">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-zinc-100 font-semibold text-sm">{{ preview.manifest.name }}</span>
                        <span class="text-[11px] font-mono text-zinc-600">{{ preview.manifest.id }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded border text-[10px] font-semibold" :class="typeConf(preview.manifest.type).cls">{{ typeConf(preview.manifest.type).label }}</span>
                    </div>
                    <div v-if="preview.is_update" class="rounded-lg border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-[12px] text-blue-300">
                        This extension is already installed ({{ preview.current_version }}) — importing will
                        <span class="font-bold">update it to {{ preview.manifest.version }}</span> and run any new migrations.
                    </div>
                    <p v-if="preview.manifest.description" class="text-zinc-400 text-sm leading-relaxed">{{ preview.manifest.description }}</p>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div><span class="text-zinc-600">Version</span><p class="text-zinc-200 font-mono mt-0.5">{{ preview.manifest.version }}</p></div>
                        <div><span class="text-zinc-600">Author</span><p class="text-zinc-200 mt-0.5">{{ preview.manifest.author ?? 'Unknown' }}</p></div>
                        <div><span class="text-zinc-600">Permissions declared</span><p class="text-zinc-200 mt-0.5">{{ preview.manifest.permissions.length }}</p></div>
                        <div><span class="text-zinc-600">Migrations</span><p class="text-zinc-200 mt-0.5">{{ preview.manifest.migrations ? 'Yes' : 'None' }}</p></div>
                        <div class="col-span-2"><span class="text-zinc-600">Registers routes</span><p class="text-zinc-200 mt-0.5">{{ Object.keys(preview.manifest.routes ?? {}).length ? Object.keys(preview.manifest.routes).join(', ') : 'None' }}</p></div>
                        <div class="col-span-2"><span class="text-zinc-600">Service provider</span><p class="text-zinc-200 font-mono mt-0.5 truncate">{{ preview.manifest.provider ?? 'None' }}</p></div>
                    </div>
                    <div v-if="preview.warnings.length" class="rounded-lg border border-amber-500/20 bg-amber-500/5 px-3 py-2">
                        <p v-for="w in preview.warnings" :key="w" class="text-amber-300 text-xs">{{ w }}</p>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-zinc-800/60 bg-[#1a1a1e] flex items-center justify-end gap-2">
                    <button type="button" class="px-3 py-2 rounded-lg text-xs font-semibold text-zinc-400 hover:text-zinc-200 transition-colors" @click="cancelImport">
                        Cancel
                    </button>
                    <button type="button" :disabled="confirming"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                        @click="confirmImport">
                        <Loader2 v-if="confirming" :size="12" :stroke-width="2" class="animate-spin" />
                        {{ confirming ? 'Installing…' : 'Install extension' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Rebuild status banner -->
        <div v-if="rebuild.status === 'building' || rebuild.status === 'pending'"
            class="mb-4 flex items-center gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-[13px]">
            <Loader2 :size="14" :stroke-width="2" class="text-amber-400 animate-spin shrink-0" />
            <span class="text-amber-300 font-semibold">Asset rebuild in progress… </span>
            <span class="text-zinc-500">Refresh the page in a moment.</span>
        </div>
        <div v-else-if="rebuild.status === 'failed'"
            class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-red-500/20 bg-red-500/5 px-4 py-3 text-[13px]">
            <div class="flex items-center gap-3">
                <AlertTriangle :size="14" :stroke-width="2" class="text-red-400 shrink-0" />
                <span class="text-red-300 font-semibold">Asset rebuild failed.</span>
                <span class="text-zinc-500">Check the queue worker logs.</span>
            </div>
            <button type="button" :disabled="rebuilding" @click="triggerRebuild"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors disabled:opacity-50">
                <Hammer :size="11" :stroke-width="2" />
                Retry rebuild
            </button>
        </div>

        <!-- Summary bar -->
        <div v-if="extensions.length > 0" class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3">
                <Puzzle :size="16" :stroke-width="1.75" class="text-zinc-500 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-zinc-100 leading-none tabular-nums">{{ summary.total }}</p>
                    <p class="text-xs text-zinc-600 mt-0.5">Installed</p>
                </div>
            </div>
            <div class="bg-emerald-500/5 border border-emerald-500/15 rounded-xl px-4 py-3 flex items-center gap-3">
                <CheckCircle2 :size="16" :stroke-width="1.75" class="text-emerald-400 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-emerald-400 leading-none tabular-nums">{{ summary.enabled }}</p>
                    <p class="text-xs text-zinc-600 mt-0.5">Enabled</p>
                </div>
            </div>
            <div class="bg-zinc-900/40 border border-zinc-800/70 rounded-xl px-4 py-3 flex items-center gap-3">
                <XCircle :size="16" :stroke-width="1.75" class="text-zinc-600 shrink-0" />
                <div>
                    <p class="text-2xl font-bold text-zinc-500 leading-none tabular-nums">{{ summary.disabled }}</p>
                    <p class="text-xs text-zinc-600 mt-0.5">Disabled</p>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="extensions.length === 0"
            class="bg-[#111113] border border-zinc-800/70 rounded-xl p-16 text-center"
        >
            <div class="w-12 h-12 rounded-2xl mx-auto mb-4 flex items-center justify-center border bg-zinc-800 border-zinc-700">
                <Puzzle :size="22" :stroke-width="1.5" class="text-zinc-500" />
            </div>
            <p class="text-zinc-200 text-base font-semibold mb-1">No extensions found</p>
            <p class="text-zinc-500 text-sm mb-6">
                Place extension folders inside
                <code class="font-mono text-blue-400">extensions/</code>
                then sync from disk.
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-400 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors"
                @click="sync"
            >
                <RefreshCw :size="13" :stroke-width="2" :class="syncing ? 'animate-spin' : ''" />
                Sync from disk
            </button>
        </div>

        <!-- Extension table — one row each, so 10-20 stay scannable -->
        <div v-else class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-800/70 bg-[#0d0d0f] text-[11px] uppercase tracking-wide text-zinc-500">
                            <th class="w-10 px-4 py-2.5">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-3 py-2.5 font-semibold">Extension</th>
                            <th class="px-3 py-2.5 font-semibold">Version</th>
                            <th class="px-3 py-2.5 font-semibold">Status</th>
                            <th class="px-3 py-2.5 font-semibold hidden md:table-cell">Author</th>
                            <th class="px-3 py-2.5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ext in extensions"
                            :key="ext.id"
                            class="border-b border-zinc-800/40 last:border-0 transition-colors hover:bg-zinc-800/20"
                            :class="!ext.enabled ? 'opacity-60' : ''"
                        >
                            <!-- Select -->
                            <td class="px-4 py-3 align-middle">
                                <input
                                    type="checkbox"
                                    :checked="selected.includes(ext.id)"
                                    class="rounded border-zinc-700 bg-zinc-800 accent-blue-500 cursor-pointer"
                                    @change="toggleOne(ext.id)"
                                />
                            </td>

                            <!-- Name + slug + type -->
                            <td class="px-3 py-3 align-middle">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 border"
                                        :class="ext.enabled ? 'bg-blue-500/10 border-blue-500/20' : 'bg-zinc-800 border-zinc-700'"
                                    >
                                        <Puzzle :size="13" :stroke-width="1.75" :class="ext.enabled ? 'text-blue-400' : 'text-zinc-600'" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <Link
                                                :href="route('admin.extensions.show', ext.id)"
                                                class="text-sm font-semibold text-zinc-100 hover:text-blue-400 transition-colors truncate"
                                                :title="ext.description || ext.name"
                                            >{{ ext.name }}</Link>
                                            <span
                                                class="inline-flex items-center px-1.5 py-0.5 rounded border text-[9px] font-semibold shrink-0"
                                                :class="typeConf(ext.type).cls"
                                            >{{ typeConf(ext.type).label }}</span>
                                            <span
                                                v-if="ext.requires_license"
                                                class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded border text-[9px] font-semibold shrink-0"
                                                :class="ext.has_license
                                                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                                    : 'bg-amber-500/10 text-amber-400 border-amber-500/20'"
                                                :title="ext.has_license ? 'License key stored' : 'Paid — a license key is required for updates'"
                                            >
                                                <KeyRound :size="8" :stroke-width="2.5" />
                                                {{ ext.has_license ? 'Licensed' : 'No key' }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] font-mono text-zinc-600 truncate">{{ ext.slug }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Version + update -->
                            <td class="px-3 py-3 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono text-zinc-300">v{{ ext.version }}</span>
                                    <button v-if="ext.update" type="button"
                                        class="flex items-center gap-1 rounded-full border border-blue-500/40 bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-400 transition hover:bg-blue-500/20 disabled:opacity-50"
                                        :disabled="updating === ext.slug"
                                        :title="ext.update.notes || `Update to v${ext.update.version}`"
                                        @click="applyUpdate(ext)">
                                        <ArrowUpCircle :size="10" :stroke-width="2" :class="updating === ext.slug ? 'animate-pulse' : ''" />
                                        {{ updating === ext.slug ? 'Updating…' : `→ v${ext.update.version}` }}
                                    </button>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="px-3 py-3 align-middle">
                                <span
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                                    :class="ext.enabled
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                        : 'bg-zinc-800 text-zinc-500 border-zinc-700'"
                                >
                                    <component :is="ext.enabled ? CheckCircle2 : XCircle" :size="9" :stroke-width="2.5" />
                                    {{ ext.enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </td>

                            <!-- Author -->
                            <td class="px-3 py-3 align-middle hidden md:table-cell">
                                <span class="text-xs text-zinc-500">{{ ext.author }}</span>
                            </td>

                            <!-- Actions -->
                            <td class="px-3 py-3 align-middle">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        v-if="ext.enabled && ext.settings_url"
                                        :href="ext.settings_url"
                                        class="flex items-center justify-center w-7 h-7 rounded-lg border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors"
                                        title="Settings"
                                    >
                                        <Settings :size="13" :stroke-width="1.75" />
                                    </a>
                                    <button
                                        type="button"
                                        :disabled="togglingId === ext.id"
                                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors disabled:opacity-60"
                                        :class="ext.enabled
                                            ? 'border-red-500/30 text-red-400 hover:bg-red-500/10'
                                            : 'bg-blue-500 text-white border-transparent hover:bg-blue-400'"
                                        @click="toggle(ext)"
                                    >
                                        <component :is="ext.enabled ? ToggleRight : ToggleLeft" :size="13" :stroke-width="2" />
                                        {{ togglingId === ext.id ? '…' : ext.enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </AdminLayout>
</template>
