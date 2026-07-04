<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { CheckCircle, XCircle, AlertCircle } from '@lucide/vue';

interface Check { label: string; value: string; passed: boolean; critical: boolean }

const props = defineProps<{ checks: Check[]; allPassed: boolean }>();
</script>

<template>
    <Head title="Requirements — Install" />

    <InstallerLayout :current-step="2">

        <div class="mb-6">
            <h2 class="text-zinc-100 text-xl font-black tracking-tight">System Requirements</h2>
            <p class="text-zinc-500 text-sm mt-1">Verifying your server meets the minimum requirements.</p>
        </div>

        <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl overflow-hidden mb-6">
            <div class="divide-y divide-zinc-800/60">
                <div
                    v-for="check in props.checks"
                    :key="check.label"
                    class="flex items-center justify-between px-5 py-3"
                >
                    <div class="flex items-center gap-2.5">
                        <component
                            :is="check.passed ? CheckCircle : XCircle"
                            :size="14"
                            :stroke-width="1.75"
                            :class="check.passed ? 'text-emerald-400' : check.critical ? 'text-red-400' : 'text-amber-400'"
                        />
                        <span class="text-zinc-200 text-sm font-mono">{{ check.label }}</span>
                    </div>
                    <span
                        class="text-xs font-medium"
                        :class="check.passed ? 'text-zinc-600' : check.critical ? 'text-red-400' : 'text-amber-400'"
                    >{{ check.value }}</span>
                </div>
            </div>
        </div>

        <div v-if="!props.allPassed" class="bg-red-500/8 border border-red-500/20 rounded-xl p-4 mb-6 flex items-start gap-3">
            <AlertCircle :size="15" :stroke-width="1.75" class="text-red-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-red-400 text-sm font-semibold">Requirements not met</p>
                <p class="text-red-400/60 text-xs mt-0.5">Please fix the issues above before continuing. Check your server's PHP configuration.</p>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <Link :href="route('installer.welcome')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
            <Link
                v-if="props.allPassed"
                :href="route('installer.database')"
                class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20"
            >
                Continue →
            </Link>
            <Link
                v-else
                :href="route('installer.requirements')"
                class="inline-flex items-center bg-zinc-900 border border-zinc-800 text-zinc-400 text-sm font-medium px-6 py-2.5 rounded-xl hover:bg-zinc-800 transition"
            >
                Re-check
            </Link>
        </div>

    </InstallerLayout>
</template>