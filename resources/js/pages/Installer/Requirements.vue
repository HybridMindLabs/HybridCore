<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { CheckCircle, XCircle, AlertCircle, Copy, Check, RefreshCw } from '@lucide/vue';

interface Check {
    group: string;
    label: string;
    value: string;
    passed: boolean;
    critical: boolean;
    fix: string | null;
}

const props = defineProps<{ checks: Check[]; allPassed: boolean }>();

/** Checks in the order the server sent them, bucketed by their group. */
const groups = computed(() => {
    const seen = new Map<string, Check[]>();

    for (const check of props.checks) {
        const bucket = seen.get(check.group) ?? [];
        bucket.push(check);
        seen.set(check.group, bucket);
    }

    return [...seen].map(([name, checks]) => ({
        name,
        checks,
        failed: checks.filter((c) => !c.passed && c.critical).length,
    }));
});

const failing = computed(() => props.checks.filter((c) => !c.passed && c.critical));

const copied = ref<string | null>(null);

async function copy(text: string) {
    await navigator.clipboard.writeText(text);
    copied.value = text;
    setTimeout(() => (copied.value = null), 1600);
}

const rechecking = ref(false);

function recheck() {
    rechecking.value = true;
    router.reload({ onFinish: () => (rechecking.value = false) });
}
</script>

<template>
    <Head title="Requirements — Install" />

    <InstallerLayout :current-step="2">

        <div class="mb-6">
            <h2 class="text-zinc-100 text-xl font-black tracking-tight">System Requirements</h2>
            <p class="text-zinc-500 text-sm mt-1">
                Checking that this server can run HybridCore. Anything that needs fixing comes with the command that fixes it.
            </p>
        </div>

        <!-- Failures first: what's wrong and exactly how to fix it -->
        <div v-if="failing.length" class="bg-red-500/[0.07] border border-red-500/20 rounded-2xl p-5 mb-6">
            <div class="flex items-start gap-3">
                <AlertCircle :size="16" :stroke-width="1.75" class="text-red-400 shrink-0 mt-0.5" />
                <div class="min-w-0 flex-1">
                    <p class="text-red-300 text-sm font-semibold">
                        {{ failing.length }} {{ failing.length === 1 ? 'check needs' : 'checks need' }} attention
                    </p>
                    <p class="text-red-400/60 text-xs mt-0.5">
                        Run the commands below on your server from the HybridCore directory, then re-check.
                    </p>

                    <div class="mt-4 space-y-3">
                        <div v-for="check in failing" :key="check.label">
                            <div class="flex items-baseline gap-2 mb-1.5">
                                <span class="text-zinc-300 text-xs font-mono font-semibold">{{ check.label }}</span>
                                <span class="text-red-400/70 text-xs">{{ check.value }}</span>
                            </div>

                            <div
                                v-if="check.fix"
                                class="group flex items-start gap-2 bg-black/40 border border-zinc-800 rounded-lg px-3 py-2.5"
                            >
                                <code class="text-zinc-300 text-xs font-mono leading-relaxed flex-1 break-all">{{ check.fix }}</code>
                                <button
                                    type="button"
                                    class="shrink-0 text-zinc-500 hover:text-zinc-300 transition p-0.5"
                                    :aria-label="`Copy fix for ${check.label}`"
                                    @click="copy(check.fix)"
                                >
                                    <component :is="copied === check.fix ? Check : Copy" :size="13" :stroke-width="1.75"
                                        :class="copied === check.fix ? 'text-emerald-400' : ''" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All checks, grouped -->
        <div class="space-y-4 mb-6">
            <div v-for="group in groups" :key="group.name">
                <div class="flex items-center gap-2 mb-2 px-1">
                    <h3 class="text-zinc-500 text-[11px] font-semibold uppercase tracking-wider">{{ group.name }}</h3>
                    <span v-if="group.failed" class="text-red-400 text-[11px] font-medium">{{ group.failed }} failing</span>
                </div>

                <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl overflow-hidden">
                    <div class="divide-y divide-zinc-800/60">
                        <div
                            v-for="check in group.checks"
                            :key="check.label"
                            class="flex items-center justify-between gap-3 px-5 py-2.5"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <component
                                    :is="check.passed ? CheckCircle : XCircle"
                                    :size="14"
                                    :stroke-width="1.75"
                                    class="shrink-0"
                                    :class="check.passed ? 'text-emerald-400' : check.critical ? 'text-red-400' : 'text-amber-400'"
                                />
                                <span class="text-zinc-200 text-sm font-mono truncate">{{ check.label }}</span>
                            </div>
                            <span
                                class="text-xs font-medium text-right shrink-0"
                                :class="check.passed ? 'text-zinc-500' : check.critical ? 'text-red-400' : 'text-amber-400'"
                            >{{ check.value }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <Link :href="route('installer.welcome')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="rechecking"
                    class="inline-flex items-center gap-1.5 bg-zinc-900 border border-zinc-800 text-zinc-400 text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-zinc-800 hover:text-zinc-200 transition disabled:opacity-50"
                    @click="recheck"
                >
                    <RefreshCw :size="13" :stroke-width="2" :class="rechecking ? 'animate-spin' : ''" />
                    Re-check
                </button>

                <Link
                    v-if="props.allPassed"
                    :href="route('installer.database')"
                    class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20"
                >
                    Continue →
                </Link>
            </div>
        </div>

    </InstallerLayout>
</template>
