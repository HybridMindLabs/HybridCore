<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { CheckCircle, Package, Puzzle, Paintbrush, ShoppingCart } from '@lucide/vue';

defineProps<{ phpVersion: string }>();
</script>

<template>
    <Head title="Install HybridCore" />

    <InstallerLayout :current-step="1">

        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/10 border border-blue-500/25 flex items-center justify-center mx-auto mb-5">
                <span class="text-blue-400 text-2xl font-black">HC</span>
            </div>
            <h1 class="text-zinc-100 text-2xl font-black tracking-tight">Welcome to HybridCore</h1>
            <p class="text-zinc-500 text-sm mt-2 max-w-md mx-auto">
                This installer will guide you through setting up your HybridCore platform in a few steps.
            </p>
        </div>

        <!-- Feature highlights -->
        <div class="grid grid-cols-2 gap-3 mb-8">
            <div
                v-for="item in [
                    { icon: Package,      label: 'Self-hosted', desc: 'Run on your own server'   },
                    { icon: Puzzle,       label: 'Extensions',  desc: 'Modular functionality'     },
                    { icon: Paintbrush,   label: 'Themes',      desc: 'Swappable public UI'       },
                    { icon: ShoppingCart, label: 'Store',       desc: 'Sell products to players'  },
                ]"
                :key="item.label"
                class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4 flex items-start gap-3"
            >
                <div class="w-8 h-8 rounded-lg bg-zinc-900 border border-zinc-800/70 flex items-center justify-center shrink-0 mt-0.5">
                    <component :is="item.icon" :size="14" :stroke-width="1.75" class="text-zinc-500" />
                </div>
                <div>
                    <p class="text-zinc-200 text-sm font-semibold">{{ item.label }}</p>
                    <p class="text-zinc-600 text-xs mt-0.5">{{ item.desc }}</p>
                </div>
            </div>
        </div>

        <!-- PHP version notice -->
        <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-4 mb-6 flex items-start gap-3">
            <CheckCircle :size="15" :stroke-width="1.75" class="text-emerald-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-zinc-400 text-sm">Running PHP <span class="text-zinc-100 font-semibold">{{ phpVersion }}</span></p>
                <p class="text-zinc-600 text-xs mt-0.5">Minimum requirement: PHP 8.3+</p>
            </div>
        </div>

        <div class="flex justify-end">
            <Link
                :href="route('installer.requirements')"
                class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20"
            >
                Start Installation →
            </Link>
        </div>

    </InstallerLayout>
</template>