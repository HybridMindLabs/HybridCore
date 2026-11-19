<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { CheckCircle2, XCircle, Loader2, Circle, ChevronRight, Terminal, Undo2 } from '@lucide/vue';
import { STEP_LABELS } from '@/constants/updateSteps';

export interface StepLog { step: string; output: string; ok?: boolean; seconds?: number }

export interface UpdateProgressState {
    running: boolean;
    plan?: string[];
    log: StepLog[];
    current?: { step: string; started_at: string; output: string } | null;
    percent?: number;
    eta_seconds?: number | null;
    elapsed_seconds?: number;
    result?: { success: boolean; message: string; rolled_back: boolean; seconds?: number; from?: string; to?: string; backup?: string | null } | null;
}

const props = defineProps<{ progress: UpdateProgressState }>();

const label = (step: string) => STEP_LABELS[step] ?? step;

// Second-by-second clock between polls, so timers move smoothly.
const now = ref(Date.now());
const tick = setInterval(() => (now.value = Date.now()), 1000);
onUnmounted(() => clearInterval(tick));
const receivedAt = ref(Date.now());
watch(() => props.progress, () => (receivedAt.value = Date.now()));

const finished = computed(() => !props.progress.running && !!props.progress.result);
// A rollback started from the history runs the same machinery with its own plan.
const isRollback = computed(() => !!props.progress.plan?.includes('git reset to previous version'));
const failed = computed(() => finished.value && !props.progress.result?.success);
const percent = computed(() => (finished.value && !failed.value ? 100 : Math.max(2, props.progress.percent ?? 0)));

const elapsed = computed(() => {
    if (finished.value) return props.progress.result?.seconds ?? props.progress.elapsed_seconds ?? 0;
    return (props.progress.elapsed_seconds ?? 0) + Math.floor((now.value - receivedAt.value) / 1000);
});
const remaining = computed(() => {
    const eta = props.progress.eta_seconds;
    if (finished.value || eta == null) return null;
    return Math.max(0, eta - Math.floor((now.value - receivedAt.value) / 1000));
});
const currentSeconds = computed(() => {
    const started = props.progress.current?.started_at;
    return started ? Math.max(0, Math.floor((now.value - new Date(started).getTime()) / 1000)) : 0;
});

const duration = (s: number) => (s >= 60 ? `${Math.floor(s / 60)}m ${String(Math.round(s % 60)).padStart(2, '0')}s` : `${Math.round(s)}s`);

/** Planned steps with their state, followed by any extra steps (a rollback). */
const rows = computed(() => {
    const plan = props.progress.plan ?? [];
    const done = new Map(props.progress.log.map((e) => [e.step, e]));
    const planned = plan.map((step) => {
        const entry = done.get(step);
        const state = entry ? (entry.ok === false ? 'failed' : 'done') : props.progress.current?.step === step ? 'running' : finished.value ? 'skipped' : 'pending';
        return { step, entry, state };
    });
    const extra: { step: string; entry: StepLog | undefined; state: string }[] = props.progress.log
        .filter((e) => !plan.includes(e.step))
        .map((entry) => ({ step: entry.step, entry, state: entry.ok === false ? 'failed' : 'done' }));
    if (props.progress.current && !plan.includes(props.progress.current.step)) {
        extra.push({ step: props.progress.current.step, entry: undefined, state: 'running' });
    }
    return { planned, extra };
});

const stepNumber = computed(() => {
    const plan = props.progress.plan ?? [];
    const index = props.progress.current ? plan.indexOf(props.progress.current.step) : -1;
    return index >= 0 ? `Step ${index + 1} of ${plan.length}` : '';
});

const open = ref<string | null>(null);
// Keep the live output scrolled to the newest line.
let terminal: HTMLElement | null = null;
const setTerminal = (el: unknown) => { terminal = el as HTMLElement | null; };
watch(() => props.progress.current?.output, async () => {
    await nextTick();
    if (terminal) terminal.scrollTop = terminal.scrollHeight;
});
</script>

<template>
    <div class="bg-admin-surface border border-zinc-800/70 rounded-xl overflow-hidden" aria-live="polite">
        <!-- Header + bar -->
        <div class="px-5 pt-5 pb-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold"
                        :class="failed ? (progress.result?.rolled_back ? 'text-blue-300' : 'text-red-400') : finished ? 'text-emerald-400' : 'text-zinc-100'">
                        <template v-if="!finished">{{ isRollback ? 'Rolling back…' : 'Updating HybridCore…' }}</template>
                        <template v-else-if="!failed">{{ isRollback ? 'Rollback complete' : 'Update complete' }}</template>
                        <template v-else-if="progress.result?.rolled_back">Update failed — previous version restored</template>
                        <template v-else>Update failed</template>
                    </p>
                    <p class="mt-0.5 text-xs text-zinc-500">
                        <template v-if="!finished && progress.current">{{ stepNumber }} · {{ label(progress.current.step) }}</template>
                        <template v-else-if="finished && progress.result?.from && progress.result?.to && progress.result.from !== progress.result.to">
                            <span class="font-mono">{{ progress.result.from }} → {{ progress.result.to }}</span>
                        </template>
                        <template v-else-if="finished">{{ progress.result?.message }}</template>
                        <template v-else>Starting…</template>
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-2xl font-bold tabular-nums text-zinc-100">{{ percent }}%</p>
                    <p class="text-[11px] text-zinc-500 tabular-nums">
                        {{ duration(elapsed) }} elapsed<template v-if="remaining !== null"> · about {{ duration(remaining) }} left</template>
                    </p>
                </div>
            </div>

            <div class="mt-3 h-2.5 rounded-full bg-zinc-800 overflow-hidden" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" aria-label="Update progress">
                <div class="h-full rounded-full transition-[width] duration-700 ease-out"
                    :class="[
                        failed ? (progress.result?.rolled_back ? 'bg-blue-500' : 'bg-red-500') : finished ? 'bg-emerald-500' : 'bg-blue-500 hc-update-stripes',
                    ]"
                    :style="{ width: `${percent}%` }" />
            </div>
            <p v-if="!finished" class="mt-2 text-[11px] text-zinc-500">
                Visitors see a maintenance page until the last step. You can leave this page — the update keeps running and this view picks it up again.
            </p>
            <p v-else-if="failed" class="mt-2 text-xs text-zinc-400">{{ progress.result?.message }}</p>
        </div>

        <!-- Steps -->
        <ol class="border-t border-zinc-800/60 divide-y divide-zinc-800/40">
            <li v-for="row in [...rows.planned, ...rows.extra]" :key="row.step">
                <button type="button" class="w-full flex items-center gap-3 px-5 py-2.5 text-left disabled:cursor-default"
                    :class="row.state === 'running' ? 'bg-blue-500/[0.06]' : 'hover:bg-white/[0.02]'"
                    :disabled="!row.entry?.output" :aria-expanded="open === row.step"
                    @click="open = open === row.step ? null : row.step">
                    <Loader2 v-if="row.state === 'running'" :size="15" class="shrink-0 text-blue-400 animate-spin" />
                    <CheckCircle2 v-else-if="row.state === 'done'" :size="15" class="shrink-0 text-emerald-500" />
                    <XCircle v-else-if="row.state === 'failed'" :size="15" class="shrink-0 text-red-400" />
                    <Undo2 v-else-if="!progress.plan?.includes(row.step) && row.step !== 'starting'" :size="15" class="shrink-0 text-blue-400" />
                    <Circle v-else :size="15" class="shrink-0 text-zinc-700" />
                    <span class="flex-1 min-w-0 truncate text-sm"
                        :class="row.state === 'pending' || row.state === 'skipped' ? 'text-zinc-500' : row.state === 'failed' ? 'text-red-300' : 'text-zinc-200'">
                        {{ label(row.step) }}
                        <span v-if="!progress.plan?.includes(row.step) && row.step !== 'starting'" class="ml-1 text-[11px] text-blue-400">rollback</span>
                        <span v-if="row.state === 'skipped'" class="ml-1 text-[11px] text-zinc-600">not run</span>
                    </span>
                    <span class="shrink-0 text-[11px] tabular-nums text-zinc-500">
                        <template v-if="row.state === 'running'">{{ duration(currentSeconds) }}</template>
                        <template v-else-if="row.entry?.seconds != null">{{ duration(row.entry.seconds) }}</template>
                    </span>
                    <ChevronRight v-if="row.entry?.output" :size="13" class="shrink-0 text-zinc-600 transition-transform" :class="open === row.step ? 'rotate-90' : ''" />
                </button>

                <!-- Live output of the running step -->
                <div v-if="row.state === 'running' && progress.current?.output" class="px-5 pb-3">
                    <pre :ref="setTerminal" class="max-h-48 overflow-y-auto rounded-lg bg-black/50 border border-zinc-800/70 p-3 text-[11px] leading-relaxed font-mono text-zinc-400 whitespace-pre-wrap break-all">{{ progress.current.output }}</pre>
                </div>
                <div v-else-if="open === row.step && row.entry?.output" class="px-5 pb-3">
                    <pre class="max-h-64 overflow-y-auto rounded-lg bg-black/40 border border-zinc-800/70 p-3 text-[11px] leading-relaxed font-mono text-zinc-500 whitespace-pre-wrap break-all">{{ row.entry.output }}</pre>
                </div>
            </li>
        </ol>

        <div v-if="finished" class="flex items-center gap-2 px-5 py-3 border-t border-zinc-800/60 bg-admin-sunken text-[11px] text-zinc-500">
            <Terminal :size="12" />
            Finished in {{ duration(elapsed) }}<template v-if="progress.result?.backup"> · database backup: <code class="font-mono text-zinc-400">{{ progress.result.backup }}</code></template>
        </div>
    </div>
</template>

<style scoped>
/* Moving stripes while a step runs, so a long build never looks frozen. */
.hc-update-stripes {
    background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.14) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.14) 50%, rgba(255, 255, 255, 0.14) 75%, transparent 75%, transparent);
    background-size: 18px 18px;
    animation: hc-update-stripes 1s linear infinite;
}

@keyframes hc-update-stripes {
    to { background-position: 18px 0; }
}

@media (prefers-reduced-motion: reduce) {
    .hc-update-stripes { animation: none; }
}
</style>
