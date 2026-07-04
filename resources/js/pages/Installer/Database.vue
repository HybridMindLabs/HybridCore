<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InstallerLayout from '@/layouts/InstallerLayout.vue';
import { Database } from '@lucide/vue';

interface Previous { db_host: string; db_port: number; db_database: string; db_username: string; db_password: string }

const props = defineProps<{ previous: Previous }>();

const form = useForm({
    db_host:     props.previous.db_host,
    db_port:     props.previous.db_port,
    db_database: props.previous.db_database,
    db_username: props.previous.db_username,
    db_password: props.previous.db_password,
});

function submit() { form.post(route('installer.database.store')); }
</script>

<template>
    <Head title="Database — Install" />

    <InstallerLayout :current-step="3">

        <div class="mb-6">
            <div class="flex items-center gap-2.5 mb-1">
                <Database :size="17" :stroke-width="1.75" class="text-blue-400" />
                <h2 class="text-zinc-100 text-xl font-black tracking-tight">Database Connection</h2>
            </div>
            <p class="text-zinc-500 text-sm mt-1">Provide your MySQL / MariaDB connection details.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="bg-[#111113] border border-zinc-800/70 rounded-2xl p-5 mb-5 flex flex-col gap-4">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Host</label>
                        <input v-model="form.db_host" type="text" placeholder="127.0.0.1" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                        <p v-if="form.errors.db_host" class="text-red-400 text-xs mt-1">{{ form.errors.db_host }}</p>
                    </div>
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Port</label>
                        <input v-model="form.db_port" type="number" placeholder="3306" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                        <p v-if="form.errors.db_port" class="text-red-400 text-xs mt-1">{{ form.errors.db_port }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Database Name</label>
                    <input v-model="form.db_database" type="text" placeholder="hybridcore" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                    <p v-if="form.errors.db_database" class="text-red-400 text-xs mt-1">{{ form.errors.db_database }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Username</label>
                        <input v-model="form.db_username" type="text" placeholder="root" autocomplete="username" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                        <p v-if="form.errors.db_username" class="text-red-400 text-xs mt-1">{{ form.errors.db_username }}</p>
                    </div>
                    <div>
                        <label class="block text-zinc-500 text-[11px] font-bold uppercase tracking-widest mb-1.5">Password</label>
                        <input v-model="form.db_password" type="password" placeholder="leave blank if none" autocomplete="current-password" class="w-full bg-zinc-900/60 border border-zinc-800 text-zinc-100 rounded-xl px-3.5 py-2.5 text-sm placeholder:text-zinc-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/10 transition" />
                        <p v-if="form.errors.db_password" class="text-red-400 text-xs mt-1">{{ form.errors.db_password }}</p>
                    </div>
                </div>

            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('installer.requirements')" class="text-zinc-500 hover:text-zinc-200 text-sm font-medium transition">← Back</Link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-md shadow-blue-500/20 disabled:opacity-50">
                    {{ form.processing ? 'Testing connection…' : 'Test & Continue →' }}
                </button>
            </div>
        </form>

    </InstallerLayout>
</template>