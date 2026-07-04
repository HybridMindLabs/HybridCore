<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { CheckCircle, AlertTriangle, Rocket } from '@lucide/vue';

defineProps<{ ready: boolean }>();

const form = useForm({});

function install() { form.post(route('installer.finish.complete')); }
</script>

<template>
    <Head title="Finish — Install" />

    <InstallerLayout :current-step="6">

        <div class="mb-6">
            <div class="flex items-center gap-2.5 mb-1">
                <Rocket :size="17" :stroke-width="1.75" class="text-blue-400" />
                <h2 class="text-zinc-100 text-xl font-black tracking-tight">Ready to Install</h2>
            </div>
            <p class="text-zinc-500 text-sm mt-1">Review what the installer will do, then click Install.</p>
        </div>

        <div v-if="ready">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl p-5 mb-5">
                <h3 class="text-zinc-300 text-sm font-bold mb-4">The installer will:</h3>
                <div class="flex flex-col gap-3">
                    <div
                        v-for="action in [
                            'Write database and application settings to .env',
                            'Generate a secure APP_KEY',
                            'Run database migrations',
                            'Create the admin account',
                            'Create the installation lock file',
                        ]"
                        :key="action"
                        class="flex items-center gap-2.5"
                    >
                        <CheckCircle :size="13" :stroke-width="1.75" class="text-emerald-400 shrink-0" />
                        <span class="text-zinc-400 text-sm">{{ action }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-amber-500/8 border border-amber-500/20 rounded-xl p-4 mb-6 flex items-start gap-3">
                <AlertTriangle :size="14" :stroke-width="1.75" class="text-amber-400 shrink-0 mt-0.5" />
                <p class="text-amber-400/80 text-xs leading-relaxed">
                    This action is irreversible. Once installed, the installer will be locked.
                    To reset, delete <code class="font-mono bg-amber-500/10 px-1 py-0.5 rounded text-[10px]">storage/installed.lock</code>.
                </p>
            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('installer.settings')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50"
                    @click="install"
                >
                    <span v-if="form.processing">Installing…</span>
                    <span v-else>Install HybridCore →</span>
                </button>
            </div>
        </div>

        <div v-else class="bg-red-500/8 border border-red-500/20 rounded-xl p-5 flex items-start gap-3 mb-6">
            <AlertTriangle :size="15" :stroke-width="1.75" class="text-red-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-red-400 text-sm font-semibold">Incomplete setup</p>
                <p class="text-red-400/60 text-xs mt-0.5">One or more installation steps have not been completed. Please go back and fill in all required information.</p>
            </div>
        </div>

        <div v-if="!ready" class="flex justify-start">
            <Link :href="route('installer.welcome')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Start over</Link>
        </div>

    </InstallerLayout>
</template>