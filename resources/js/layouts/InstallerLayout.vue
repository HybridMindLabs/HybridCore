<script setup lang="ts">
interface Step { number: number; label: string }

defineProps<{ currentStep: number }>();

const steps: Step[] = [
    { number: 1, label: 'Welcome'      },
    { number: 2, label: 'Requirements' },
    { number: 3, label: 'Database'     },
    { number: 4, label: 'Admin'        },
    { number: 5, label: 'Settings'     },
    { number: 6, label: 'Finish'       },
];
</script>

<template>
    <div class="min-h-screen bg-[#09090b] flex flex-col" style="background-image: radial-gradient(circle, rgba(255,255,255,0.025) 1px, transparent 1px); background-size: 24px 24px;">

        <!-- Header -->
        <header class="border-b border-zinc-800/70 bg-[#111113]/80 backdrop-blur-sm px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-blue-500/10 border border-blue-500/30 flex items-center justify-center">
                    <span class="text-blue-400 text-xs font-black leading-none">HC</span>
                </div>
                <span class="text-zinc-200 text-sm font-semibold">HybridCore Installer</span>
            </div>
            <span class="text-zinc-600 text-xs font-mono">v0.2.0</span>
        </header>

        <!-- Step indicator -->
        <div class="bg-[#111113]/60 border-b border-zinc-800/70 px-6 py-3.5">
            <div class="max-w-2xl mx-auto flex items-center gap-1">
                <template v-for="(step, index) in steps" :key="step.number">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <div
                            class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 border transition-all"
                            :class="step.number < currentStep
                                ? 'bg-blue-500/15 border-blue-500/40 text-blue-400'
                                : step.number === currentStep
                                    ? 'bg-blue-500 border-blue-500 text-white'
                                    : 'bg-zinc-900 border-zinc-800 text-zinc-600'"
                        >
                            <span v-if="step.number < currentStep">✓</span>
                            <span v-else>{{ step.number }}</span>
                        </div>
                        <span
                            class="text-xs font-medium hidden sm:block truncate transition-colors"
                            :class="step.number === currentStep ? 'text-zinc-100' : step.number < currentStep ? 'text-blue-400' : 'text-zinc-600'"
                        >{{ step.label }}</span>
                    </div>
                    <div
                        v-if="index < steps.length - 1"
                        class="flex-1 h-px min-w-[8px]"
                        :class="step.number < currentStep ? 'bg-blue-500/25' : 'bg-zinc-800/70'"
                    />
                </template>
            </div>
        </div>

        <!-- Content -->
        <main class="flex-1 flex items-start justify-center px-6 py-10">
            <div class="w-full max-w-2xl">
                <slot />
            </div>
        </main>

        <!-- Footer -->
        <footer class="px-6 py-4 border-t border-zinc-800/70 text-center">
            <p class="text-zinc-700 text-xs">HybridCore &mdash; Early Development &mdash; Not for production use</p>
        </footer>

    </div>
</template>