<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import StepHeader from '@/components/Installer/StepHeader.vue';
import { Rocket, AlertTriangle, Database, ShieldCheck, Globe, Loader2, ArrowRight } from '@lucide/vue';

interface Summary {
    database: { server: string; name: string; user: string } | null;
    admin: { name: string; email: string } | null;
    settings: { name: string; url: string; locale: string; timezone: string } | null;
}

const props = defineProps<{ ready: boolean; summary: Summary }>();

const form = useForm({});

function install() { form.post(route('installer.finish.complete')); }

const groups = [
    { key: 'database', icon: Database, title: 'Database', back: 'installer.database' },
    { key: 'admin', icon: ShieldCheck, title: 'Your account', back: 'installer.admin' },
    { key: 'settings', icon: Globe, title: 'Site', back: 'installer.settings' },
] as const;

const labels: Record<string, string> = {
    server: 'Server', name: 'Name', user: 'User', email: 'Email',
    url: 'Address', locale: 'Language', timezone: 'Timezone',
};

function rows(key: keyof Summary) {
    const data = props.summary[key];
    return data ? Object.entries(data) : [];
}
</script>

<template>
    <Head title="Finish — Install" />

    <InstallerLayout :current-step="6">

        <template v-if="ready">
            <StepHeader
                :icon="Rocket"
                title="Review and install"
                description="Everything you entered, before anything is written. This is the first step that changes your server — until now nothing has been saved."
            />

            <!-- What was entered -->
            <div class="space-y-3 mb-5">
                <div
                    v-for="group in groups"
                    :key="group.key"
                    class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl overflow-hidden"
                >
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-zinc-800/60">
                        <div class="flex items-center gap-2">
                            <component :is="group.icon" :size="13" :stroke-width="1.75" class="text-blue-400/70" />
                            <h3 class="text-zinc-300 text-xs font-bold">{{ group.title }}</h3>
                        </div>
                        <Link :href="route(group.back)" class="text-zinc-600 hover:text-zinc-300 text-[11px] font-medium transition">Edit</Link>
                    </div>
                    <dl class="px-4 py-3 space-y-1.5">
                        <div v-for="[key, value] in rows(group.key)" :key="key" class="flex items-baseline justify-between gap-4">
                            <dt class="text-zinc-600 text-xs shrink-0">{{ labels[key] ?? key }}</dt>
                            <dd class="text-zinc-300 text-xs font-mono text-right truncate">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- What will happen -->
            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-5 mb-5">
                <h3 class="text-zinc-300 text-sm font-bold mb-3">Installing will</h3>
                <ol class="space-y-2">
                    <li
                        v-for="(action, i) in [
                            'Save your database and site settings to the .env file.',
                            'Generate the encryption key that secures sessions and passwords.',
                            'Create the database tables.',
                            'Create your administrator account and sign you in.',
                            'Lock the installer so it cannot be run again.',
                        ]"
                        :key="i"
                        class="flex items-start gap-2.5"
                    >
                        <span class="w-4 h-4 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-500 text-[9px] font-bold flex items-center justify-center shrink-0 mt-0.5">{{ i + 1 }}</span>
                        <span class="text-zinc-500 text-xs leading-relaxed">{{ action }}</span>
                    </li>
                </ol>
                <p class="text-zinc-600 text-xs mt-4 pt-3.5 border-t border-zinc-800/60 leading-relaxed">
                    This takes a few seconds. If anything fails, nothing is locked — you can correct it and try again.
                    To re-run setup later, delete
                    <code class="font-mono text-zinc-500 bg-zinc-900 border border-zinc-800 rounded px-1.5 py-0.5 text-[11px]">storage/installed.lock</code>.
                </p>
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('installer.settings')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-60"
                    @click="install"
                >
                    <Loader2 v-if="form.processing" :size="14" :stroke-width="2.25" class="animate-spin" />
                    {{ form.processing ? 'Installing…' : 'Install HybridCore' }}
                    <ArrowRight v-if="!form.processing" :size="14" :stroke-width="2.25" />
                </button>
            </div>
        </template>

        <template v-else>
            <StepHeader
                :icon="AlertTriangle"
                title="Some steps are missing"
                description="Setup needs your database details, an administrator account and your site settings before it can run. One of them has not been filled in — this can happen if the session expired."
            />

            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl overflow-hidden mb-6">
                <div
                    v-for="group in groups"
                    :key="group.key"
                    class="flex items-center justify-between gap-3 px-4 py-3 border-b border-zinc-800/60 last:border-0"
                >
                    <div class="flex items-center gap-2.5">
                        <component
                            :is="group.icon"
                            :size="13"
                            :stroke-width="1.75"
                            :class="summary[group.key] ? 'text-emerald-400' : 'text-red-400'"
                        />
                        <span class="text-zinc-300 text-sm">{{ group.title }}</span>
                    </div>
                    <Link
                        v-if="!summary[group.key]"
                        :href="route(group.back)"
                        class="text-blue-400 hover:text-blue-300 text-xs font-medium transition"
                    >Complete this step →</Link>
                    <span v-else class="text-zinc-600 text-xs">Done</span>
                </div>
            </div>

            <Link :href="route('installer.welcome')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Start over</Link>
        </template>

    </InstallerLayout>
</template>
