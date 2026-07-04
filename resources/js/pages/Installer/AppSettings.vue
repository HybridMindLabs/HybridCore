<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { Globe } from '@lucide/vue';

interface Previous { app_name: string; app_url: string; app_locale: string; app_timezone: string }

const props = defineProps<{ previous: Previous; locales: Record<string, string>; timezones: Record<string, string> }>();

const form = useForm({
    app_name:     props.previous.app_name,
    app_url:      props.previous.app_url,
    app_locale:   props.previous.app_locale,
    app_timezone: props.previous.app_timezone,
});

function submit() { form.post(route('installer.settings.store')); }
</script>

<template>
    <Head title="Site Settings — Install" />

    <InstallerLayout :current-step="5">

        <div class="mb-6">
            <div class="flex items-center gap-2.5 mb-1">
                <Globe :size="17" :stroke-width="1.75" class="text-blue-400" />
                <h2 class="text-zinc-100 text-xl font-black tracking-tight">Site Settings</h2>
            </div>
            <p class="text-zinc-500 text-sm mt-1">Configure your platform's basic settings.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl p-5 mb-5 flex flex-col gap-4">

                <div>
                    <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Application Name</label>
                    <input v-model="form.app_name" type="text" placeholder="HybridCore" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                    <p v-if="form.errors.app_name" class="text-red-400 text-xs mt-1">{{ form.errors.app_name }}</p>
                </div>

                <div>
                    <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Application URL</label>
                    <input v-model="form.app_url" type="url" placeholder="https://yoursite.com" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                    <p v-if="form.errors.app_url" class="text-red-400 text-xs mt-1">{{ form.errors.app_url }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Default Language</label>
                        <select v-model="form.app_locale" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition">
                            <option v-for="(label, code) in props.locales" :key="code" :value="code">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.app_locale" class="text-red-400 text-xs mt-1">{{ form.errors.app_locale }}</p>
                    </div>
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Timezone</label>
                        <select v-model="form.app_timezone" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition">
                            <option v-for="(label, tz) in props.timezones" :key="tz" :value="tz">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.app_timezone" class="text-red-400 text-xs mt-1">{{ form.errors.app_timezone }}</p>
                    </div>
                </div>

            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('installer.admin')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50">
                    {{ form.processing ? 'Saving…' : 'Continue →' }}
                </button>
            </div>
        </form>

    </InstallerLayout>
</template>