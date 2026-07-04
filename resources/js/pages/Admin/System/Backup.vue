<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    DatabaseBackup, Download, Upload, Trash2, AlertTriangle,
    FileJson, Settings, Puzzle, Palette, FileText, Server,
    PackageOpen, Clock, HardDrive, Database, CheckCircle2, XCircle,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';
import { ref } from 'vue';

interface Counts { settings: number; extensions: number; themes: number; pages: number; menus: number }
interface StoredBackup { filename: string; type: 'json' | 'sql'; size_kb: number; created_at: string }

const props = defineProps<{ counts: Counts; backups: StoredBackup[]; mysqldump_available: boolean }>();

const dbBacking = ref(false);
function runDatabaseBackup() {
    if (!confirm('Run mysqldump now? This may take a moment for large databases.')) return;
    dbBacking.value = true;
    router.post(route('admin.backup.database'), {}, {
        onFinish: () => { dbBacking.value = false; },
    });
}

const importForm = useForm({ backup_file: null as File | null });
const fileInput = ref<HTMLInputElement | null>(null);
const importError = ref('');

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    importForm.backup_file = file;
    importError.value = '';
}

function submitImport() {
    if (!importForm.backup_file) return;
    importForm.post(route('admin.backup.import'), {
        forceFormData: true,
        onError: (errors) => { importError.value = errors.backup_file ?? 'Import failed.'; },
        onSuccess: () => { importForm.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
}

function deleteBackup(filename: string) {
    if (confirm(`Delete backup "${filename}"? This cannot be undone.`)) {
        router.delete(route('admin.backup.delete', filename));
    }
}

const exports = [
    { label: 'Settings',   detail: 'Platform settings (secrets excluded)', route: 'admin.backup.export.settings',   countKey: 'settings'   as const, icon: Settings  },
    { label: 'Extensions', detail: 'Installed extension list',             route: 'admin.backup.export.extensions', countKey: 'extensions' as const, icon: Puzzle    },
    { label: 'Themes',     detail: 'Installed theme list',                 route: 'admin.backup.export.themes',     countKey: 'themes'     as const, icon: Palette   },
    { label: 'Content',    detail: 'Pages and menus',                      route: 'admin.backup.export.content',    countKey: 'pages'      as const, icon: FileText  },
];
</script>

<template>
    <Head title="Backup & Export" />
    <AdminLayout title="Backup & Export">

        <PageHeader title="Backup & Export" description="Generate, download, and restore platform backups." :icon="DatabaseBackup">
            <template #actions>
                <a
                    :href="route('admin.backup.export.all')"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                >
                    <PackageOpen :size="12" :stroke-width="2" />
                    Generate Full Backup
                </a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-5 items-start">

            <!-- Left column -->
            <div class="flex flex-col gap-5">

                <!-- Full backup hero card -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center shrink-0">
                            <PackageOpen :size="18" :stroke-width="1.5" class="text-blue-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-zinc-100 text-sm font-semibold mb-1">Full Platform Backup</h3>
                            <p class="text-zinc-500 text-xs leading-relaxed mb-4">
                                Exports all settings, extensions, themes, and content into a single
                                <code class="text-blue-400 font-mono">.json</code> file.
                                The file is also saved to <code class="text-blue-400 font-mono">storage/app/backups/</code> for later download.
                                Secrets and database rows are excluded.
                            </p>
                            <a
                                :href="route('admin.backup.export.all')"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors"
                            >
                                <Download :size="14" :stroke-width="2" />
                                Generate &amp; Download
                            </a>
                        </div>
                    </div>
                </div>

                <!-- MySQL Backup -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                            :class="mysqldump_available
                                ? 'bg-emerald-500/10 border-emerald-500/20'
                                : 'bg-zinc-800 border-zinc-700'"
                        >
                            <Database :size="18" :stroke-width="1.5" :class="mysqldump_available ? 'text-emerald-400' : 'text-zinc-600'" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-zinc-100 text-sm font-semibold">MySQL Database Backup</h3>
                                <span
                                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[10px] font-semibold"
                                    :class="mysqldump_available
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                        : 'bg-zinc-800 text-zinc-500 border-zinc-700'"
                                >
                                    <component :is="mysqldump_available ? CheckCircle2 : XCircle" :size="9" :stroke-width="2.5" />
                                    {{ mysqldump_available ? 'mysqldump available' : 'mysqldump not found' }}
                                </span>
                            </div>
                            <p class="text-zinc-500 text-xs leading-relaxed mb-4">
                                Runs <code class="text-blue-400 font-mono">mysqldump --single-transaction</code> using
                                your <code class="text-blue-400 font-mono">.env</code> credentials.
                                The <code class="text-blue-400 font-mono">.sql</code> file is saved to
                                <code class="text-blue-400 font-mono">storage/app/backups/</code>.
                            </p>
                            <button
                                type="button"
                                :disabled="!mysqldump_available || dbBacking"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="runDatabaseBackup"
                            >
                                <Database :size="14" :stroke-width="2" />
                                {{ dbBacking ? 'Running mysqldump…' : 'Backup Database Now' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Section exports -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <FileJson :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Section Exports</span>
                        <span class="ml-auto text-xs text-zinc-600">{{ exports.length }} sections</span>
                    </div>
                    <div
                        v-for="exp in exports"
                        :key="exp.route"
                        class="flex items-center justify-between px-5 py-3.5 border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-lg bg-zinc-900/60 border border-zinc-800/70 flex items-center justify-center shrink-0">
                                <component :is="exp.icon" :size="14" :stroke-width="1.75" class="text-zinc-500" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-zinc-100 text-sm font-medium flex items-center gap-2">
                                    {{ exp.label }}
                                    <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 border border-zinc-700/60">{{ counts[exp.countKey] }}</span>
                                </p>
                                <p class="text-zinc-600 text-xs mt-0.5">{{ exp.detail }}</p>
                            </div>
                        </div>
                        <a
                            :href="route(exp.route)"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600 transition-colors shrink-0 ml-4"
                        >
                            <Download :size="12" :stroke-width="2" />
                            Export
                        </a>
                    </div>
                </div>

                <!-- Stored backups -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <HardDrive :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Stored Backups</span>
                        <span class="ml-auto text-xs text-zinc-600">storage/app/backups/</span>
                    </div>

                    <div v-if="backups.length > 0">
                        <div
                            v-for="b in backups"
                            :key="b.filename"
                            class="flex items-center gap-3 px-5 py-3 border-b border-zinc-800/40 last:border-0 hover:bg-zinc-900/30 transition-colors"
                        >
                            <component
                                :is="b.type === 'sql' ? Database : FileJson"
                                :size="14" :stroke-width="1.75"
                                :class="b.type === 'sql' ? 'text-emerald-600' : 'text-zinc-600'"
                                class="shrink-0"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-zinc-300 text-xs font-mono truncate">{{ b.filename }}</p>
                                <p class="text-zinc-600 text-[11px] flex items-center gap-2 mt-0.5">
                                    <Clock :size="10" :stroke-width="1.75" />
                                    {{ b.created_at }}
                                    <span class="text-zinc-700">·</span>
                                    {{ b.size_kb }} KB
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <a
                                    :href="route('admin.backup.download', b.filename)"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-500 hover:text-blue-400 hover:bg-blue-500/10 transition-colors"
                                    title="Download"
                                >
                                    <Download :size="13" :stroke-width="1.75" />
                                </a>
                                <button
                                    type="button"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-zinc-600 hover:text-red-400 hover:bg-red-500/10 transition-colors"
                                    title="Delete"
                                    @click="deleteBackup(b.filename)"
                                >
                                    <Trash2 :size="13" :stroke-width="1.75" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-10 text-center">
                        <HardDrive :size="24" :stroke-width="1.25" class="text-zinc-800 mx-auto mb-2" />
                        <p class="text-zinc-600 text-sm">No stored backups yet.</p>
                        <p class="text-zinc-700 text-xs mt-0.5">Generate a full backup to create one.</p>
                    </div>
                </div>

                <!-- Import backup -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <Upload :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Import Backup</span>
                    </div>
                    <div class="p-5">
                        <p class="text-zinc-500 text-xs mb-4 leading-relaxed">
                            Upload a <code class="text-blue-400 font-mono">hybridcore-backup-*.json</code> file to restore
                            settings and content. Extensions and themes must be reinstalled manually.
                        </p>

                        <div
                            v-if="importError"
                            class="flex items-center gap-2 mb-3 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-xs"
                        >
                            <AlertTriangle :size="13" :stroke-width="1.75" class="shrink-0" />
                            {{ importError }}
                        </div>

                        <form @submit.prevent="submitImport" class="flex flex-col gap-3">
                            <div
                                class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg border border-dashed border-zinc-700 bg-zinc-900/40 hover:border-zinc-600 transition-colors cursor-pointer"
                                @click="fileInput?.click()"
                            >
                                <FileJson :size="16" :stroke-width="1.75" class="text-zinc-600 shrink-0" />
                                <span class="text-xs text-zinc-500">
                                    {{ importForm.backup_file ? importForm.backup_file.name : 'Click to choose a .json backup file…' }}
                                </span>
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept=".json"
                                    class="hidden"
                                    @change="onFileChange"
                                />
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="!importForm.backup_file || importForm.processing"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                                >
                                    <Upload :size="13" :stroke-width="2" />
                                    {{ importForm.processing ? 'Restoring…' : 'Restore Backup' }}
                                </button>
                                <p v-if="importForm.recentlySuccessful" class="text-emerald-400 text-xs">Restored successfully.</p>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-4">

                <!-- Warning -->
                <div class="flex items-start gap-3 bg-amber-500/5 border border-amber-500/20 rounded-xl px-4 py-4">
                    <AlertTriangle :size="15" :stroke-width="1.75" class="text-amber-400 mt-0.5 shrink-0" />
                    <div>
                        <p class="text-zinc-100 text-sm font-semibold mb-1.5">Not full database backups</p>
                        <p class="text-zinc-400 text-xs leading-relaxed">
                            These exports cover platform configuration and content only.
                            For full backups configure <code class="text-blue-400 font-mono">mysqldump</code>
                            or managed database snapshots server-side.
                        </p>
                    </div>
                </div>

                <!-- What's included in full backup -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                        <Server :size="13" :stroke-width="1.75" class="text-zinc-600" />
                        <span class="text-sm font-semibold text-zinc-100">Full backup includes</span>
                    </div>
                    <div class="divide-y divide-zinc-800/40">
                        <div v-for="exp in exports" :key="exp.label" class="flex items-center justify-between px-4 py-2.5">
                            <div class="flex items-center gap-2 text-xs text-zinc-400">
                                <component :is="exp.icon" :size="12" :stroke-width="1.75" class="text-zinc-600" />
                                {{ exp.label }}
                            </div>
                            <span class="text-xs font-mono text-zinc-500 tabular-nums">{{ counts[exp.countKey] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Import notes -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-800/70">
                        <p class="text-sm font-semibold text-zinc-100">Import notes</p>
                    </div>
                    <ul class="p-4 flex flex-col gap-2">
                        <li
                            v-for="note in [
                                'Settings are merged (existing values overwritten)',
                                'Pages and menus are upserted by slug',
                                'Extensions and themes are NOT restored — reinstall manually',
                                'Sensitive keys (passwords, secrets) are skipped',
                            ]"
                            :key="note"
                            class="flex items-start gap-2 text-xs text-zinc-500 leading-relaxed"
                        >
                            <span class="w-1 h-1 rounded-full bg-zinc-600 mt-1.5 shrink-0" />
                            {{ note }}
                        </li>
                    </ul>
                </div>

                <!-- Server-side backup -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-800/70">
                        <p class="text-sm font-semibold text-zinc-100">Server-side backup</p>
                        <p class="text-xs text-zinc-600 mt-0.5">Run on a schedule via cron</p>
                    </div>
                    <div class="p-4 flex flex-col gap-2">
                        <code class="text-[11px] font-mono text-zinc-400 bg-zinc-900/60 border border-zinc-800/70 rounded px-3 py-2 block break-all leading-relaxed">mysqldump -u root -p db_name &gt; backup.sql</code>
                        <code class="text-[11px] font-mono text-zinc-400 bg-zinc-900/60 border border-zinc-800/70 rounded px-3 py-2 block break-all leading-relaxed">tar -czf storage.tar.gz storage/app</code>
                    </div>
                </div>

            </div>
        </div>

    </AdminLayout>
</template>
