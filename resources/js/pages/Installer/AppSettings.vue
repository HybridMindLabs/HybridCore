<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import StepHeader from '@/components/Installer/StepHeader.vue';
import Field from '@/components/Installer/Field.vue';
import { Globe, Info, ArrowRight, Loader2 } from '@lucide/vue';

interface Previous { app_name: string; app_url: string; app_locale: string; app_timezone: string }

const props = defineProps<{ previous: Previous; locales: Record<string, string>; timezones: Record<string, string> }>();

const form = useForm({
    app_name: props.previous.app_name,
    app_url: props.previous.app_url,
    app_locale: props.previous.app_locale,
    app_timezone: props.previous.app_timezone,
});

function submit() { form.post(route('installer.settings.store')); }

const selectClasses =
    'w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition';
</script>

<template>
    <Head title="Site settings — Install" />

    <InstallerLayout :current-step="5">

        <StepHeader
            :icon="Globe"
            title="Name your community"
            description="The basics your site presents to players. Every one of these can be changed later from the admin panel — nothing here is permanent."
        />

        <form @submit.prevent="submit">
            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-2xl p-5 mb-5 flex flex-col gap-4">

                <Field
                    v-model="form.app_name"
                    label="Community name"
                    placeholder="My Gaming Community"
                    :error="form.errors.app_name"
                    hint="Shown in the site header, page titles and emails you send to players."
                />

                <Field
                    v-model="form.app_url"
                    label="Site address"
                    type="url"
                    placeholder="https://example.com"
                    :error="form.errors.app_url"
                    hint="The full public address, including https://. Links in emails are built from this, so get it right — a wrong value sends players to a dead link."
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-zinc-400 text-xs font-semibold mb-1.5">Default language</label>
                        <select v-model="form.app_locale" :class="selectClasses">
                            <option v-for="(label, code) in props.locales" :key="code" :value="code">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.app_locale" class="text-red-400 text-xs mt-1.5">{{ form.errors.app_locale }}</p>
                        <p v-else class="text-zinc-600 text-xs mt-1.5 leading-relaxed">Players can pick their own.</p>
                    </div>
                    <div>
                        <label class="block text-zinc-400 text-xs font-semibold mb-1.5">Timezone</label>
                        <select v-model="form.app_timezone" :class="selectClasses">
                            <option v-for="(label, tz) in props.timezones" :key="tz" :value="tz">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.app_timezone" class="text-red-400 text-xs mt-1.5">{{ form.errors.app_timezone }}</p>
                        <p v-else class="text-zinc-600 text-xs mt-1.5 leading-relaxed">Used for timestamps and scheduled jobs.</p>
                    </div>
                </div>

            </div>

            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-4 mb-6 flex items-start gap-3">
                <Info :size="14" :stroke-width="1.75" class="text-blue-400/70 shrink-0 mt-0.5" />
                <p class="text-zinc-500 text-xs leading-relaxed">
                    Email delivery, Steam and Discord sign-in, your game servers and extensions are all configured
                    after setup, from the admin panel. This step only covers what the site needs to start.
                </p>
            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('installer.admin')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50"
                >
                    <Loader2 v-if="form.processing" :size="14" :stroke-width="2.25" class="animate-spin" />
                    {{ form.processing ? 'Saving…' : 'Continue' }}
                    <ArrowRight v-if="!form.processing" :size="14" :stroke-width="2.25" />
                </button>
            </div>
        </form>

    </InstallerLayout>
</template>
