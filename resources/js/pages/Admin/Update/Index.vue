<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { GitBranch, RefreshCw, ArrowDownToLine, CheckCircle2, AlertCircle, Circle, Clock, Terminal } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

interface Commit {
    hash: string;
    subject: string;
    author: string;
    date: string;
}

interface StepLog {
    step: string;
    output: string;
}

interface Release {
    version: string; name: string; url: string; notes: string;
    published_at: string | null; is_newer: boolean;
}

const props = defineProps<{
    currentCommit: { hash: string; subject: string; date: string };
    currentBranch: string;
    version: string;
    isGitInstall: boolean;
    panelUpdatesEnabled: boolean;
    latestRelease: Release | null;
}>();

const checking  = ref(false);
const applying  = ref(false);
const checked   = ref(false);
const upToDate  = ref(false);
const commits   = ref<Commit[]>([]);
const applyLog  = ref<StepLog[]>([]);
const applied   = ref(false);
const error     = ref('');
const currentCommit = ref(props.currentCommit);

async function checkForUpdates() {
    checking.value = true;
    error.value = '';
    try {
        const res = await fetch(route('admin.updates.check'), { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '', 'Accept': 'application/json' } });
        const data = await res.json();
        commits.value = data.commits ?? [];
        upToDate.value = data.upToDate ?? false;
        checked.value = true;
    } catch (e) {
        error.value = 'Failed to check for updates. Make sure git is available in the server environment.';
    } finally {
        checking.value = false;
    }
}

async function applyUpdate() {
    if (!confirm('Apply update? This will run git pull, composer install, and php artisan migrate.')) return;
    applying.value = true;
    applyLog.value = [];
    error.value = '';
    try {
        const res = await fetch(route('admin.updates.apply'), { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '', 'Accept': 'application/json' } });
        const data = await res.json();
        applyLog.value = data.log ?? [];
        if (data.commit) currentCommit.value = data.commit;
        applied.value = true;
        commits.value = [];
        upToDate.value = true;
    } catch (e) {
        error.value = 'Update failed. Check server logs.';
    } finally {
        applying.value = false;
    }
}
</script>

<template>
    <Head title="System Update" />
    <AdminLayout title="System Update">

        <PageHeader title="System Update" description="Update the application from GitHub" :icon="RefreshCw" />

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_280px] gap-5 items-start">
        <div class="flex flex-col gap-5">

            <!-- New release banner -->
            <div
                v-if="latestRelease?.is_newer"
                class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-5"
            >
                <div class="flex items-start gap-3">
                    <ArrowDownToLine :size="18" :stroke-width="1.75" class="text-blue-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-blue-300 text-sm font-bold">
                            HybridCore {{ latestRelease.version }} is available
                            <span class="text-blue-400/60 font-normal">— you run {{ version }}</span>
                        </p>
                        <p v-if="latestRelease.notes" class="text-zinc-400 text-xs mt-1.5 line-clamp-4 whitespace-pre-line">{{ latestRelease.notes }}</p>
                        <div class="flex items-center gap-3 mt-3">
                            <a
                                v-if="latestRelease.url"
                                :href="latestRelease.url" target="_blank" rel="noopener"
                                class="text-blue-400 text-xs font-semibold hover:underline"
                            >Release notes ↗</a>
                            <span v-if="!isGitInstall" class="text-zinc-500 text-xs">
                                ZIP install — replace the files, then run
                                <code class="text-zinc-300 bg-zinc-800 px-1 py-0.5 rounded text-[11px]">php artisan hybridcore:update --local</code>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-else-if="latestRelease"
                class="bg-[#111113] border border-zinc-800/70 rounded-xl px-5 py-3 flex items-center gap-2"
            >
                <CheckCircle2 :size="14" :stroke-width="1.75" class="text-emerald-400" />
                <span class="text-zinc-400 text-xs">You are on the latest release ({{ version }}).</span>
            </div>

            <div v-if="!panelUpdatesEnabled" class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-5 py-3">
                <span class="text-amber-400 text-xs">Panel updates are disabled on this installation (HYBRIDCORE_PANEL_UPDATES=false) — use the CLI.</span>
            </div>

            <!-- Current version card -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <h3 class="text-zinc-400 text-xs font-semibold uppercase tracking-wider mb-4">Current Version</h3>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center shrink-0">
                            <GitBranch :size="15" class="text-blue-400" :stroke-width="1.75" />
                        </div>
                        <div>
                            <p class="text-xs text-zinc-600 mb-0.5">Branch</p>
                            <p class="text-sm font-mono text-zinc-200">{{ currentBranch }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-3 border-t border-zinc-800/50">
                        <div class="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center shrink-0">
                            <Circle :size="10" class="text-emerald-400 fill-emerald-400" :stroke-width="0" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-zinc-600 mb-0.5">Latest commit</p>
                            <p class="text-sm text-zinc-200 truncate">{{ currentCommit.subject || '—' }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-mono text-zinc-600">{{ currentCommit.hash || '—' }}</p>
                            <p class="text-[11px] text-zinc-700 flex items-center justify-end gap-1 mt-0.5">
                                <Clock :size="11" :stroke-width="1.75" />
                                {{ currentCommit.date || '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check for updates -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-zinc-400 text-xs font-semibold uppercase tracking-wider">Available Updates</h3>
                    <button
                        type="button"
                        :disabled="checking || applying"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors disabled:opacity-40"
                        :class="checking ? 'border-zinc-800 text-zinc-600' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:border-zinc-600'"
                        @click="checkForUpdates"
                    >
                        <RefreshCw :size="12" :stroke-width="1.75" :class="checking ? 'animate-spin' : ''" />
                        {{ checking ? 'Checking…' : 'Check now' }}
                    </button>
                </div>

                <!-- Error -->
                <div v-if="error" class="flex items-start gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm mb-4">
                    <AlertCircle :size="15" :stroke-width="1.75" class="shrink-0 mt-0.5" />
                    {{ error }}
                </div>

                <!-- Not yet checked -->
                <div v-if="!checked && !error" class="text-center py-8">
                    <RefreshCw :size="22" :stroke-width="1.25" class="mx-auto mb-3 text-zinc-700" />
                    <p class="text-zinc-600 text-sm">Click "Check now" to fetch updates from GitHub</p>
                </div>

                <!-- Up to date -->
                <div v-else-if="checked && upToDate && !applied" class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <CheckCircle2 :size="18" class="text-emerald-400 shrink-0" :stroke-width="1.75" />
                    <p class="text-sm text-emerald-400 font-medium">You are up to date!</p>
                </div>

                <!-- Applied successfully -->
                <div v-else-if="applied" class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <CheckCircle2 :size="18" class="text-emerald-400 shrink-0" :stroke-width="1.75" />
                    <p class="text-sm text-emerald-400 font-medium">Update applied successfully!</p>
                </div>

                <!-- Pending commits -->
                <div v-else-if="checked && commits.length > 0">
                    <div class="flex flex-col divide-y divide-zinc-800/60 mb-4">
                        <div v-for="c in commits" :key="c.hash" class="py-3 flex items-start gap-3">
                            <span class="text-[11px] font-mono text-zinc-600 mt-0.5 shrink-0 w-14">{{ c.hash.slice(0, 7) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-300 truncate">{{ c.subject }}</p>
                                <p class="text-[11px] text-zinc-600 mt-0.5">{{ c.author }} · {{ c.date }}</p>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="applying"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-colors disabled:opacity-40 bg-blue-500 text-white hover:bg-blue-400"
                        @click="applyUpdate"
                    >
                        <ArrowDownToLine :size="15" :stroke-width="2" :class="applying ? 'animate-bounce' : ''" />
                        {{ applying ? 'Applying update…' : `Apply ${commits.length} update${commits.length !== 1 ? 's' : ''}` }}
                    </button>
                </div>
            </div>

            <!-- Apply log -->
            <div v-if="applyLog.length > 0" class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 border-b border-zinc-800/60 bg-[#0d0d0f]">
                    <Terminal :size="14" :stroke-width="1.75" class="text-zinc-600" />
                    <h3 class="text-zinc-400 text-xs font-semibold">Update Log</h3>
                </div>
                <div class="divide-y divide-zinc-800/40">
                    <div v-for="step in applyLog" :key="step.step" class="px-5 py-3">
                        <p class="text-xs font-mono text-zinc-500 mb-1.5">$ {{ step.step }}</p>
                        <pre v-if="step.output" class="text-[11px] font-mono text-zinc-600 whitespace-pre-wrap break-all">{{ step.output }}</pre>
                        <p v-else class="text-[11px] text-zinc-700 italic">No output</p>
                    </div>
                </div>
            </div>

        </div><!-- end left col -->

        <!-- Right sidebar -->
        <div class="flex flex-col gap-4">

            <!-- Branch / commit info -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                    <GitBranch :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">Repository</span>
                </div>
                <div class="divide-y divide-zinc-800/40">
                    <div class="px-4 py-3 flex flex-col gap-0.5">
                        <p class="text-[11px] text-zinc-600 font-medium uppercase tracking-wider">Branch</p>
                        <p class="text-sm font-mono text-blue-400">{{ currentBranch }}</p>
                    </div>
                    <div class="px-4 py-3 flex flex-col gap-0.5">
                        <p class="text-[11px] text-zinc-600 font-medium uppercase tracking-wider">Commit</p>
                        <p class="text-xs font-mono text-zinc-400">{{ currentCommit.hash?.slice(0, 7) || '—' }}</p>
                    </div>
                    <div class="px-4 py-3 flex flex-col gap-0.5">
                        <p class="text-[11px] text-zinc-600 font-medium uppercase tracking-wider">Date</p>
                        <p class="text-xs text-zinc-400 flex items-center gap-1">
                            <Clock :size="11" :stroke-width="1.75" />
                            {{ currentCommit.date || '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- What happens on update -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                    <Terminal :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">What runs</span>
                </div>
                <div class="p-3 flex flex-col gap-1.5">
                    <div
                        v-for="(cmd, i) in ['git pull', 'composer install', 'php artisan migrate', 'cache:clear']"
                        :key="i"
                        class="flex items-center gap-2"
                    >
                        <span class="w-4 h-4 rounded-full bg-zinc-800 border border-zinc-700/60 text-[9px] font-bold text-zinc-500 flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                        <code class="text-xs font-mono text-zinc-400">{{ cmd }}</code>
                    </div>
                </div>
            </div>

        </div><!-- end right col -->

        </div><!-- end grid -->

    </AdminLayout>
</template>
