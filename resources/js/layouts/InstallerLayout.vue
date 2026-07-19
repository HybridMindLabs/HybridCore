<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';

interface Step { number: number; label: string }

defineProps<{ currentStep: number }>();

const version = computed(() => (usePage().props.app as { version?: string })?.version ?? '');

// Semver: a 0.x line is pre-1.0 by definition, so the badge retires itself the
// day 1.0.0 ships rather than being a string someone has to remember to delete.
const isPreRelease = computed(() => version.value.startsWith('0.'));

const steps: Step[] = [
    { number: 1, label: 'Welcome' },
    { number: 2, label: 'Requirements' },
    { number: 3, label: 'Database' },
    { number: 4, label: 'Account' },
    { number: 5, label: 'Settings' },
    { number: 6, label: 'Finish' },
];
</script>

<template>
    <div class="min-h-screen bg-[#09090b] flex flex-col">
        <!-- Ambient wash: keeps the page from reading as a flat black rectangle -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 26px 26px;" />
            <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[42rem] h-[42rem] rounded-full opacity-[0.07] blur-[110px] bg-blue-500" />
        </div>

        <header class="relative border-b border-zinc-800/60 bg-[#0c0c0e]/80 backdrop-blur-md">
            <div class="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-blue-500 flex items-center justify-center shrink-0 shadow-lg shadow-blue-500/25">
                        <span class="text-white text-[11px] font-black leading-none tracking-tight">HC</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-zinc-100 text-sm font-bold leading-tight">HybridCore</p>
                        <p class="text-zinc-500 text-[11px] leading-tight">Setup</p>
                    </div>
                </div>

                <div v-if="version" class="flex items-center gap-2 shrink-0">
                    <span
                        v-if="isPreRelease"
                        class="text-amber-400/90 text-[10px] font-semibold uppercase tracking-wider bg-amber-500/10 border border-amber-500/20 rounded-md px-2 py-0.5"
                        title="A 0.x release. The platform works, but features and APIs can still change between versions."
                    >Pre-release</span>
                    <span class="text-zinc-500 text-xs font-mono">v{{ version }}</span>
                </div>
            </div>
        </header>

        <div class="relative border-b border-zinc-800/60 bg-[#0c0c0e]/40">
            <div class="max-w-3xl mx-auto px-6 py-3.5">
                <!-- Rail: numbers on desktop, a progress bar on narrow screens -->
                <div class="hidden sm:flex items-center gap-1.5">
                    <template v-for="(step, index) in steps" :key="step.number">
                        <div class="flex items-center gap-2 min-w-0">
                            <div
                                class="w-[22px] h-[22px] rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 border transition-all duration-300"
                                :class="step.number < currentStep
                                    ? 'bg-blue-500/15 border-blue-500/40 text-blue-400'
                                    : step.number === currentStep
                                        ? 'bg-blue-500 border-blue-500 text-white shadow-md shadow-blue-500/30'
                                        : 'bg-zinc-900 border-zinc-800 text-zinc-500'"
                            >
                                <Check v-if="step.number < currentStep" :size="11" :stroke-width="3" />
                                <span v-else>{{ step.number }}</span>
                            </div>
                            <span
                                class="text-xs font-medium truncate transition-colors duration-300"
                                :class="step.number === currentStep ? 'text-zinc-100' : step.number < currentStep ? 'text-blue-400/80' : 'text-zinc-500'"
                            >{{ step.label }}</span>
                        </div>
                        <div
                            v-if="index < steps.length - 1"
                            class="flex-1 h-px min-w-[10px] transition-colors duration-300"
                            :class="step.number < currentStep ? 'bg-blue-500/30' : 'bg-zinc-800/60'"
                        />
                    </template>
                </div>

                <div class="sm:hidden">
                    <div class="flex items-baseline justify-between mb-2">
                        <span class="text-zinc-100 text-xs font-semibold">{{ steps[currentStep - 1]?.label }}</span>
                        <span class="text-zinc-500 text-[11px] font-mono">{{ currentStep }} / {{ steps.length }}</span>
                    </div>
                    <div class="h-1 bg-zinc-800/70 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-blue-500 rounded-full transition-all duration-500"
                            :style="{ width: `${(currentStep / steps.length) * 100}%` }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <main class="relative flex-1 px-6 py-10">
            <div class="w-full max-w-3xl mx-auto">
                <slot />
            </div>
        </main>

        <footer class="relative border-t border-zinc-800/60 px-6 py-4">
            <div class="max-w-3xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-zinc-500 text-xs">
                    HybridCore<span v-if="version" class="font-mono"> v{{ version }}</span>
                    <span class="text-zinc-800"> · </span>
                    <span>Self-hosted community platform</span>
                </p>
                <div class="flex items-center gap-4">
                    <a href="https://github.com/HybridMindLabs/HybridCore/wiki" target="_blank" rel="noopener"
                       class="text-zinc-500 hover:text-zinc-300 text-xs transition">Documentation</a>
                    <a href="https://github.com/HybridMindLabs/HybridCore" target="_blank" rel="noopener"
                       class="text-zinc-500 hover:text-zinc-300 text-xs transition">GitHub</a>
                </div>
            </div>
        </footer>
    </div>
</template>
