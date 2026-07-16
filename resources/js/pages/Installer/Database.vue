<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import StepHeader from '@/components/Installer/StepHeader.vue';
import Field from '@/components/Installer/Field.vue';
import { Database, Info, ArrowRight, Loader2 } from '@lucide/vue';

interface Previous { db_host: string; db_port: number; db_database: string; db_username: string; db_password: string }

const props = defineProps<{ previous: Previous }>();

const form = useForm({
    db_host: props.previous.db_host,
    db_port: props.previous.db_port,
    db_database: props.previous.db_database,
    db_username: props.previous.db_username,
    db_password: props.previous.db_password,
});

function submit() { form.post(route('installer.database.store')); }
</script>

<template>
    <Head title="Database — Install" />

    <InstallerLayout :current-step="3">

        <StepHeader
            :icon="Database"
            title="Connect your database"
            description="HybridCore keeps everything — players, servers, news — in a MySQL or MariaDB database. Create an empty one first, then enter its details here. Nothing is saved until the connection works."
        />

        <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-xl p-4 mb-5 flex items-start gap-3">
            <Info :size="14" :stroke-width="1.75" class="text-blue-400/70 shrink-0 mt-0.5" />
            <p class="text-zinc-500 text-xs leading-relaxed">
                No database yet? Create one in your host's control panel under <span class="text-zinc-400">MySQL Databases</span>,
                or on the command line with
                <code class="font-mono text-zinc-400 bg-zinc-900 border border-zinc-800 rounded px-1.5 py-0.5 text-[11px]">CREATE DATABASE hybridcore;</code>
                It should be empty — the final step fills it.
            </p>
        </div>

        <form @submit.prevent="submit">
            <div class="bg-[#111113]/80 border border-zinc-800/70 rounded-2xl p-5 mb-5 flex flex-col gap-4">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <Field
                            v-model="form.db_host"
                            label="Host"
                            placeholder="127.0.0.1"
                            :error="form.errors.db_host"
                            hint="Almost always 127.0.0.1 — the database runs on this same server."
                        />
                    </div>
                    <Field
                        v-model="form.db_port"
                        label="Port"
                        type="number"
                        placeholder="3306"
                        :error="form.errors.db_port"
                        hint="3306 unless changed."
                    />
                </div>

                <Field
                    v-model="form.db_database"
                    label="Database name"
                    placeholder="hybridcore"
                    :error="form.errors.db_database"
                    hint="The empty database you created. Some hosts prefix it with your account name, e.g. myaccount_hybridcore."
                />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field
                        v-model="form.db_username"
                        label="Username"
                        placeholder="hybridcore"
                        autocomplete="username"
                        :error="form.errors.db_username"
                        hint="A user with full rights on that database."
                    />
                    <Field
                        v-model="form.db_password"
                        label="Password"
                        type="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        optional
                        :error="form.errors.db_password"
                        hint="Leave blank if the user has no password."
                    />
                </div>

            </div>

            <div class="flex items-center justify-between gap-4">
                <Link :href="route('installer.requirements')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-1.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50"
                >
                    <Loader2 v-if="form.processing" :size="14" :stroke-width="2.25" class="animate-spin" />
                    {{ form.processing ? 'Testing connection…' : 'Test connection' }}
                    <ArrowRight v-if="!form.processing" :size="14" :stroke-width="2.25" />
                </button>
            </div>
        </form>

    </InstallerLayout>
</template>
