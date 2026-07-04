<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, Info, GitBranch, CheckCircle2, Tag } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import PageHeader from '@/components/UI/PageHeader.vue';

const props = defineProps<{ version: string; channel: string }>();

const form = useForm({ channel: props.channel });

function saveChannel() {
    form.put(route('admin.updates.channel'));
}

const inputClass = 'bg-zinc-900/60 border border-zinc-800/70 text-zinc-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/20';

const channels = [
    { value: 'stable', label: 'Stable',  desc: 'Recommended for production',   color: 'text-emerald-400' },
    { value: 'beta',   label: 'Beta',    desc: 'Early access to new features',  color: 'text-blue-400'    },
    { value: 'dev',    label: 'Dev',     desc: 'Bleeding edge, may be unstable', color: 'text-amber-400'  },
];
</script>

<template>
    <Head title="Updates" />
    <AdminLayout title="Updates">

        <PageHeader title="Updates" description="HybridCore version and update channel." :icon="Download" />

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-4 items-start">

            <!-- Left -->
            <div class="flex flex-col gap-4">

                <!-- Current version card -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5 flex items-center gap-5">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center shrink-0">
                        <Tag :size="20" :stroke-width="1.5" class="text-blue-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-zinc-500 text-xs font-medium mb-1">Installed version</p>
                        <p class="text-blue-400 text-3xl font-bold font-mono leading-none">{{ version }}</p>
                    </div>
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold shrink-0">
                        <CheckCircle2 :size="12" :stroke-width="2" />
                        Up to date
                    </div>
                </div>

                <!-- Channel selector -->
                <div class="bg-[#111113] border border-zinc-800/70 rounded-xl p-5">
                    <h3 class="text-zinc-100 text-sm font-semibold mb-1">Update Channel</h3>
                    <p class="text-zinc-600 text-xs mb-4">Controls which releases are offered when automatic updates are available.</p>

                    <form @submit.prevent="saveChannel">
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <button
                                v-for="ch in channels"
                                :key="ch.value"
                                type="button"
                                class="flex flex-col items-start px-4 py-3 rounded-xl border transition-colors text-left"
                                :class="form.channel === ch.value
                                    ? 'border-blue-500/60 bg-blue-500/10'
                                    : 'border-zinc-800 hover:border-zinc-700'"
                                @click="form.channel = ch.value"
                            >
                                <span class="text-xs font-semibold" :class="form.channel === ch.value ? 'text-blue-400' : 'text-zinc-300'">{{ ch.label }}</span>
                                <span class="text-[11px] text-zinc-600 mt-0.5">{{ ch.desc }}</span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <p v-if="form.recentlySuccessful" class="text-emerald-400 text-sm">Saved.</p>
                            <span v-else />
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 text-white hover:bg-blue-400 transition-colors disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving…' : 'Save Channel' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notice -->
                <div class="flex items-start gap-3 bg-blue-500/5 border border-blue-500/20 rounded-xl px-4 py-4">
                    <Info :size="15" :stroke-width="1.75" class="text-blue-400 mt-0.5 shrink-0" />
                    <div>
                        <p class="text-zinc-100 text-sm font-medium mb-1">Automatic updates coming soon</p>
                        <p class="text-zinc-400 text-xs leading-relaxed">
                            Remote update checks, release notes, and backup-before-update will arrive in a future release.
                            For now update via
                            <code class="text-blue-400 font-mono">git pull</code> and
                            <code class="text-blue-400 font-mono">composer install</code>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right: manual update steps -->
            <div class="bg-[#111113] border border-zinc-800/70 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-800/70 flex items-center gap-2">
                    <GitBranch :size="13" :stroke-width="1.75" class="text-zinc-600" />
                    <span class="text-sm font-semibold text-zinc-100">Manual Update</span>
                </div>
                <div class="p-4 flex flex-col gap-3">
                    <p class="text-zinc-500 text-xs">Run these commands on your server to update manually:</p>
                    <div
                        v-for="(cmd, i) in [
                            'git pull origin main',
                            'composer install --no-dev',
                            'php artisan migrate --force',
                            'php artisan config:clear',
                            'php artisan cache:clear',
                            'npm install && npm run build',
                        ]"
                        :key="i"
                        class="flex items-center gap-2.5"
                    >
                        <span class="w-5 h-5 rounded-full bg-zinc-800 border border-zinc-700/60 text-[10px] font-bold text-zinc-500 flex items-center justify-center shrink-0">{{ i + 1 }}</span>
                        <code class="text-xs font-mono text-zinc-300 bg-zinc-900/60 border border-zinc-800/70 rounded px-2 py-1 flex-1 break-all">{{ cmd }}</code>
                    </div>
                </div>
            </div>

        </div>

    </AdminLayout>
</template>
