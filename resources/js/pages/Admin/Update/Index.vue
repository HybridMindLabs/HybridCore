<script setup lang="ts">
import { confirm } from '@/composables/useConfirm';
import { requireSudo } from '@/composables/useSudo';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { GitBranch, RefreshCw, ArrowDownToLine, CheckCircle2, AlertCircle, Circle, Clock, Terminal, XCircle, History, ShieldAlert, Loader2 } from '@lucide/vue';
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
    ok?: boolean;
}

interface HistoryEntry { id: number; from: string | null; to: string | null; by: string | null; at: string | null }

interface UpdateResult { success: boolean; message: string; log: StepLog[]; rolled_back: boolean; backup: string | null; commit?: Props['currentCommit'] }

interface Release {
    version: string; name: string; url: string; notes: string;
    published_at: string | null; is_newer: boolean; prerelease?: boolean;
}

type Props = {
    currentCommit: { hash: string; subject: string; date: string };
    currentBranch: string;
    version: string;
    isGitInstall: boolean;
    panelUpdatesEnabled: boolean;
    latestRelease: Release | null;
    preflight: string[];
    history: HistoryEntry[];
};

const props = defineProps<Props>();

const checking  = ref(false);
const applying  = ref(false);
const checked   = ref(false);
const upToDate  = ref(false);
const commits   = ref<Commit[]>([]);
const applyLog  = ref<StepLog[]>([]);
const applied   = ref(false);
const error     = ref('');
const rolledBack = ref(false);
const backupFile = ref<string | null>(null);
const problems  = ref<string[]>(props.preflight ?? []);
const currentCommit = ref(props.currentCommit);
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

function showResult(data: UpdateResult) {
    applyLog.value = data.log ?? [];
    rolledBack.value = !!data.rolled_back;
    backupFile.value = data.backup ?? null;

    if (!data.success) {
        error.value = data.message || 'Update failed. Check the log below.';
        return;
    }

    if (data.commit) currentCommit.value = data.commit;
    applied.value = true;
    commits.value = [];
    upToDate.value = true;
}

/** Follow an update whose own request was cut off by a proxy/PHP timeout. */
async function followProgress() {
    applying.value = true;
    for (;;) {
        await new Promise((resolve) => setTimeout(resolve, 3000));
        try {
            const res = await fetch(route('admin.updates.status'), { headers: { Accept: 'application/json' } });
            if (!res.ok) continue; // maintenance mode or a restart in progress
            const progress = await res.json();
            applyLog.value = progress.log ?? applyLog.value;
            if (!progress.running) {
                if (progress.result) showResult(progress.result);
                break;
            }
        } catch {
            // the server is busy swapping code; keep waiting
        }
    }
    applying.value = false;
}

onMounted(async () => {
    const res = await fetch(route('admin.updates.status'), { headers: { Accept: 'application/json' } }).catch(() => null);
    if (res?.ok && (await res.json()).running) followProgress();
});

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
    if (!await confirm('Apply update? The site goes into maintenance mode while it pulls, rebuilds, and migrates — then comes back up automatically.')) return;
    applying.value = true;
    applyLog.value = [];
    error.value = '';
    rolledBack.value = false;
    let following = false;
    try {
        const post = () => fetch(route('admin.updates.apply'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } });
        let res = await post();
        if (res.status === 423) {
            if (!await requireSudo()) return;
            res = await post();
        }

        const data = await res.json().catch(() => null);

        if (res.status === 409 && data?.problems) {
            problems.value = data.problems;
            error.value = data.message;
            return;
        }

        if (!data) {
            // Gateway timeout: the update keeps running server-side.
            following = true;
            followProgress();
            return;
        }

        showResult(data);
    } catch {
        following = true;
        followProgress();
    } finally {
        if (!following) applying.value = false;
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
                            <span v-if="latestRelease.prerelease" class="ml-1 align-middle text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded border border-amber-500/40 text-amber-300">Pre-release</span>
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
                class="bg-admin-surface border border-zinc-800/70 rounded-xl px-5 py-3 flex items-center gap-2"
            >
                <CheckCircle2 :size="14" :stroke-width="1.75" class="text-emerald-400" />
                <span class="text-zinc-400 text-xs">You are on the latest release ({{ version }}).</span>
            </div>

            <div v-if="!panelUpdatesEnabled" class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-5 py-3">
                <span class="text-amber-400 text-xs">Panel updates are disabled on this installation (HYBRIDCORE_PANEL_UPDATES=false) — use the CLI.</span>
            </div>

            <!-- Current version card -->
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
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
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl p-5">
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
                        <Loader2 v-if="applying" :size="15" :stroke-width="2" class="animate-spin" />
                        <ArrowDownToLine v-else :size="15" :stroke-width="2" />
                        {{ applying ? 'Applying update…' : `Apply ${commits.length} update${commits.length !== 1 ? 's' : ''}` }}
                    </button>
                </div>
            </div>

            <!-- Pre-flight problems -->
            <div v-if="problems.length" class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-5 py-4" role="alert">
                <div class="flex items-start gap-3">
                    <ShieldAlert :size="16" :stroke-width="2" class="text-amber-400 mt-0.5 shrink-0" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-amber-200">The update can't start yet</p>
                        <ul class="mt-1.5 list-disc pl-4 space-y-1 text-xs text-amber-100/80">
                            <li v-for="p in problems" :key="p">{{ p }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Rolled back -->
            <div v-if="rolledBack" class="bg-blue-500/10 border border-blue-500/30 rounded-xl px-5 py-4">
                <p class="text-sm font-semibold text-blue-200">The previous version was restored</p>
                <p class="text-xs text-blue-100/70 mt-1">The update failed partway, so its migrations and code were rolled back. The site is running exactly as before.<span v-if="backupFile"> A database backup was taken first: <code class="font-mono">{{ backupFile }}</code>.</span></p>
            </div>

            <!-- Following a long-running update -->
            <div v-if="applying && applyLog.length" class="flex items-center gap-2 text-xs text-zinc-400">
                <Loader2 :size="14" class="animate-spin" /> Updating… {{ applyLog[applyLog.length - 1]?.step }}
            </div>

            <!-- Apply log -->
            <div v-if="applyLog.length > 0" class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-3 border-b border-zinc-800/60 bg-admin-sunken">
                    <Terminal :size="14" :stroke-width="1.75" class="text-zinc-600" />
                    <h3 class="text-zinc-400 text-xs font-semibold">Update Log</h3>
                </div>
                <div class="divide-y divide-zinc-800/40">
                    <div v-for="(step, i) in applyLog" :key="`${i}-${step.step}`" class="px-5 py-3">
                        <p class="text-xs font-mono mb-1.5 flex items-center gap-1.5" :class="step.ok === false ? 'text-red-400' : 'text-zinc-500'">
                            <XCircle v-if="step.ok === false" :size="12" />
                            <CheckCircle2 v-else-if="step.ok" :size="12" class="text-emerald-500" />
                            $ {{ step.step }}
                        </p>
                        <pre v-if="step.output" class="text-[11px] font-mono text-zinc-600 whitespace-pre-wrap break-all">{{ step.output }}</pre>
                        <p v-else class="text-[11px] text-zinc-700 italic">No output</p>
                    </div>
                </div>
            </div>

        </div><!-- end left col -->

        <!-- Right sidebar -->
        <div class="flex flex-col gap-4">

            <!-- Branch / commit info -->
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
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
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                    <Terminal :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">What runs</span>
                </div>
                <div class="p-3 flex flex-col gap-1.5">
                    <div
                        v-for="(cmd, i) in [
                            'maintenance mode on',
                            'git pull --ff-only',
                            'fix folder permissions',
                            'composer install',
                            'database backup (MySQL/MariaDB)',
                            'php artisan migrate',
                            'npm ci && npm run build',
                            'restart SSR renderer (if enabled)',
                            'optimize:clear',
                            'queue:restart',
                            'maintenance mode off',
                        ]"
                        :key="i"
                        class="flex items-center gap-2"
                    >
                        <span class="w-4 h-4 rounded-full bg-zinc-800 border border-zinc-700/60 text-[9px] font-bold text-zinc-500 flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                        <code class="text-xs font-mono text-zinc-400">{{ cmd }}</code>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-1">If any step after the pull fails, this run's migrations are rolled back and the previous code is restored, so the site comes back on the version it had.</p>
                </div>
            </div>

            <!-- Update history -->
            <div class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                    <History :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">Recent updates</span>
                </div>
                <ul v-if="history.length" class="divide-y divide-zinc-800/50">
                    <li v-for="h in history" :key="h.id" class="px-4 py-2.5">
                        <p class="text-xs font-mono text-zinc-300">{{ h.from || '—' }} → {{ h.to || '—' }}</p>
                        <p class="text-[11px] text-zinc-500">{{ h.at }}<span v-if="h.by"> · {{ h.by }}</span></p>
                    </li>
                </ul>
                <p v-else class="px-4 py-4 text-xs text-zinc-500">No updates applied from the panel yet.</p>
            </div>

        </div><!-- end right col -->

        </div><!-- end grid -->

    </AdminLayout>
</template>
